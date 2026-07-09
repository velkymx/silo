<?php

namespace App\Services\Dashboard;

use App\Models\Bookmark;
use App\Models\File;
use App\Models\RssFeed;
use App\Models\RssItem;
use App\Models\ShareLink;
use App\Models\User;
use App\Services\QuotaService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Assembles the home-screen payload. Each public method answers one of the
 * four home-screen questions and returns a small, presentation-ready DTO (or
 * null) so the controller stays thin and the Vue components stay dumb.
 *
 * This first slice answers "where do I continue?" via jumpBackIn(). Sibling
 * methods (continueWorking, whatsNew, needsAttention, workspaceSummary) land
 * in later tasks.
 */
class DashboardService
{
    /** Consecutive fetch failures before a feed is "failing" (red). */
    private const FEED_FAILURE_THRESHOLD = 3;

    /** A healthy feed with no success in this many days is "stale" (blue). */
    private const FEED_STALE_DAYS = 7;

    /** Storage at or above this percent of quota needs a decision (yellow). */
    private const STORAGE_WARN_PERCENT = 85;

    /** A share expiring within this many hours needs a decision (yellow). */
    private const SHARE_EXPIRY_HOURS = 48;

    public function __construct(private readonly QuotaService $quota) {}

    /**
     * The user's most recently *content-edited* file or note — the top
     * "Jump Back In" CTA. Returns null when the user has never edited
     * anything (the card is hidden rather than faked).
     *
     * `content_edited_at` is the honest edit signal: it is set on note
     * autosave and file-version writes, not on plain uploads, so a freshly
     * uploaded-but-untouched file never masquerades as "where you left off".
     */
    public function jumpBackIn(User $user): ?JumpBackInItem
    {
        $file = File::query()
            ->where('owner_id', $user->id)
            ->files()
            ->whereNotNull('content_edited_at')
            ->orderByDesc('content_edited_at')
            ->first();

        if ($file === null) {
            return null;
        }

        $isNote = $file->mime === 'text/markdown';

        return new JumpBackInItem(
            id: $file->id,
            title: $file->name,
            type: $isNote ? 'note' : 'file',
            url: $isNote
                ? route('notes.index', ['open' => $file->id])
                : route('files.index', ['folder' => $file->parent_id]),
            editedAt: $file->content_edited_at->toIso8601String(),
        );
    }

    /**
     * The "Continue Working" card: recently-touched, likely-unfinished objects
     * mixed across modules, newest first, capped at $limit. Each source is
     * queried for its own $limit then merged and re-sorted, so a burst in one
     * module can't crowd out the others' newest items.
     *
     * Sources (each an open loop):
     *  - files/notes edited recently (`content_edited_at`),
     *  - bookmarks added but not yet filed (no category),
     *  - articles recently read (resume the feed).
     *
     * @return array<int, array{type: string, title: string, url: string, at: string, reason: string}>
     */
    public function continueWorking(User $user, int $limit = 6): array
    {
        /** @var Collection<int, array{at: Carbon, item: ContinueItem}> $rows */
        $rows = collect();

        File::query()->where('owner_id', $user->id)->files()
            ->whereNotNull('content_edited_at')
            ->orderByDesc('content_edited_at')->limit($limit)
            ->get(['id', 'name', 'mime', 'parent_id', 'content_edited_at'])
            ->each(function (File $file) use ($rows): void {
                $isNote = $file->mime === 'text/markdown';
                $rows->push([
                    'at' => $file->content_edited_at,
                    'item' => new ContinueItem(
                        type: $isNote ? 'note' : 'file',
                        title: $file->name,
                        url: $isNote
                            ? route('notes.index', ['open' => $file->id])
                            : route('files.index', ['folder' => $file->parent_id]),
                        at: $file->content_edited_at->toIso8601String(),
                        reason: 'edited',
                    ),
                ]);
            });

        Bookmark::query()->where('owner_id', $user->id)
            ->where(fn ($q) => $q->whereNull('category')->orWhere('category', ''))
            ->orderByDesc('created_at')->limit($limit)
            ->get(['id', 'title', 'created_at'])
            ->each(fn (Bookmark $bookmark) => $rows->push([
                'at' => $bookmark->created_at,
                'item' => new ContinueItem(
                    type: 'bookmark',
                    title: $bookmark->title,
                    url: route('bookmarks.index'),
                    at: $bookmark->created_at->toIso8601String(),
                    reason: 'added',
                ),
            ]));

        RssItem::query()->where('user_id', $user->id)
            ->whereNotNull('read_at')
            ->orderByDesc('read_at')->limit($limit)
            ->get(['id', 'title', 'read_at'])
            ->each(fn (RssItem $article) => $rows->push([
                'at' => $article->read_at,
                'item' => new ContinueItem(
                    type: 'article',
                    title: $article->title,
                    url: route('rss.items.show', ['item' => $article->id]),
                    at: $article->read_at->toIso8601String(),
                    reason: 'read',
                ),
            ]));

        return $rows
            ->sortByDesc(fn (array $row) => $row['at']->getTimestamp())
            ->take($limit)
            ->map(fn (array $row) => $row['item']->toArray())
            ->values()
            ->all();
    }

    /**
     * The "What's New" card: the unread RSS count plus the newest $limit unread
     * articles (title + feed). Returns null when nothing is unread — the card is
     * hidden rather than shown empty (silence communicates confidence).
     */
    public function whatsNew(User $user, int $limit = 5): ?WhatsNew
    {
        $unread = RssItem::query()->where('user_id', $user->id)->where('is_read', false);

        $unreadCount = (clone $unread)->count();
        if ($unreadCount === 0) {
            return null;
        }

        $articles = $unread
            ->orderByDesc('published_at')->limit($limit)
            ->get(['id', 'title', 'feed_title'])
            ->map(fn (RssItem $item) => [
                'id' => $item->id,
                'title' => $item->title,
                'feed' => $item->feed_title,
                'url' => route('rss.items.show', ['item' => $item->id]),
            ])
            ->all();

        return new WhatsNew($unreadCount, $articles, route('rss.index'));
    }

    /**
     * The "Needs Attention" card: only abnormalities in the user's own data,
     * split into red -> yellow -> blue tiers. Returns [] when nothing is wrong
     * (the frontend shows a quiet "All clear", never a wall of green ticks).
     *
     * @return array<int, array{tier: string, title: string, url: string}>
     */
    public function needsAttention(User $user): array
    {
        /** @var Collection<int, AttentionItem> $items */
        $items = collect();

        $this->collectRed($user, $items);
        $this->collectYellow($user, $items);
        $this->collectBlue($user, $items);

        return $items->map->toArray()->all();
    }

    /** Red: something is broken. */
    private function collectRed(User $user, Collection $items): void
    {
        $infected = File::query()->where('owner_id', $user->id)->files()
            ->where('status', File::STATUS_INFECTED)->count();
        if ($infected > 0) {
            $items->push(new AttentionItem(
                AttentionItem::TIER_RED,
                "{$infected} ".Str::plural('file', $infected).' failed a virus scan',
                route('files.index'),
            ));
        }

        $failed = File::query()->where('owner_id', $user->id)->files()
            ->where('status', File::STATUS_FAILED)->count();
        if ($failed > 0) {
            $items->push(new AttentionItem(
                AttentionItem::TIER_RED,
                "{$failed} ".Str::plural('file', $failed).' failed to process',
                route('files.index'),
            ));
        }

        $failingFeeds = RssFeed::query()->where('user_id', $user->id)
            ->where('consecutive_failures', '>=', self::FEED_FAILURE_THRESHOLD)->count();
        if ($failingFeeds > 0) {
            $items->push(new AttentionItem(
                AttentionItem::TIER_RED,
                "{$failingFeeds} ".Str::plural('feed', $failingFeeds).' failing to update',
                route('rss.index'),
            ));
        }
    }

    /** Yellow: needs a decision soon. */
    private function collectYellow(User $user, Collection $items): void
    {
        ['used' => $used, 'quota' => $quota] = $this->quota->summary($user->id);
        if ($quota > 0) {
            $percent = (int) floor($used / $quota * 100);
            if ($percent >= self::STORAGE_WARN_PERCENT) {
                $items->push(new AttentionItem(
                    AttentionItem::TIER_YELLOW,
                    "Storage is {$percent}% full",
                    route('storage.index'),
                ));
            }
        }

        $expiring = ShareLink::query()->where('created_by', $user->id)
            ->whereNotNull('expires_at')
            ->whereBetween('expires_at', [now(), now()->addHours(self::SHARE_EXPIRY_HOURS)])
            ->count();
        if ($expiring > 0) {
            $items->push(new AttentionItem(
                AttentionItem::TIER_YELLOW,
                "{$expiring} share ".Str::plural('link', $expiring).' '.($expiring === 1 ? 'expires' : 'expire').' soon',
                route('files.index'),
            ));
        }

        $dead = Bookmark::query()->where('owner_id', $user->id)
            ->where('status', Bookmark::STATUS_DEAD)->count();
        if ($dead > 0) {
            $items->push(new AttentionItem(
                AttentionItem::TIER_YELLOW,
                "{$dead} dead ".Str::plural('bookmark', $dead),
                route('bookmarks.index'),
            ));
        }
    }

    /** Blue: worth a glance. */
    private function collectBlue(User $user, Collection $items): void
    {
        $stale = RssFeed::query()->where('user_id', $user->id)
            ->where('consecutive_failures', 0)
            ->whereNotNull('last_success_at')
            ->where('last_success_at', '<', now()->subDays(self::FEED_STALE_DAYS))
            ->count();
        if ($stale > 0) {
            $items->push(new AttentionItem(
                AttentionItem::TIER_BLUE,
                "{$stale} ".Str::plural('feed', $stale).' '.($stale === 1 ? 'has' : 'have')." not updated in over a week",
                route('rss.index'),
            ));
        }
    }
}
