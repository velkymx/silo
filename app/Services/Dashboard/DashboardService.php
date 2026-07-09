<?php

namespace App\Services\Dashboard;

use App\Models\Bookmark;
use App\Models\File;
use App\Models\RssItem;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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
}
