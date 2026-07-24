<?php

namespace App\Services\Rss;

use App\Models\RssFeed;
use App\Models\RssItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Single source of truth for "which inbox items match the current view":
 * smart folder, feed, author/exclude, boolean search, feed combinations,
 * top-feeds, and the user's blocked keywords.
 *
 * Both the inbox listing (FeedController::index) and bulk mark-all-read
 * (ItemController::markAllRead) run through this so "mark all read" always
 * operates on exactly the set the user is looking at — no filter can drift
 * between the two.
 */
class InboxItemQuery
{
    public function __construct(private BooleanSearchParser $search) {}

    public function apply(Builder $query, Request $request, int $userId): Builder
    {
        $filter = $request->string('filter')->lower()->toString();
        $feedId = $request->integer('feed');
        $search = trim((string) $request->string('search')->toString());
        $author = trim((string) $request->string('author')->toString());
        $exclude = trim((string) $request->string('exclude')->toString());
        $feedIdsParam = array_values(array_filter(array_map(
            'intval',
            explode(',', (string) $request->string('feeds')->toString())
        )));
        $blocked = $request->user()?->blocked_keywords ?? [];
        $topFeedIds = $filter === 'top_feeds' ? $this->topFeedIds($userId) : [];

        return $query
            ->inboxFilter($filter, $feedId, $author, $exclude)
            ->when($search !== '', fn ($q) => $this->search->apply($q, $search))
            ->when($feedIdsParam !== [], fn ($q) => $q->whereIn('feed_id', $feedIdsParam))
            ->when($filter === 'top_feeds' && $topFeedIds !== [], fn ($q) => $q->whereIn('feed_id', $topFeedIds))
            ->when($filter === 'top_feeds' && $topFeedIds === [], fn ($q) => $q->whereRaw('0 = 1'))
            ->when($blocked !== [], function ($q) use ($blocked) {
                $q->where(function ($w) use ($blocked) {
                    foreach ($blocked as $kw) {
                        $kw = trim((string) $kw);
                        if ($kw === '') {
                            continue;
                        }
                        $w->where('title', 'not like', "%{$kw}%")
                            ->where('excerpt', 'not like', "%{$kw}%");
                    }
                });
            });
    }

    /**
     * @return array<int, int>
     */
    private function topFeedIds(int $userId): array
    {
        return RssItem::ownedBy($userId)
            ->whereIn('feed_id', RssFeed::ownedBy($userId)->whereNull('muted_at')->pluck('id'))
            ->where('published_at', '>=', now()->subDays(7))
            ->selectRaw('feed_id, count(*) as c')
            ->groupBy('feed_id')
            ->orderByDesc('c')
            ->limit(5)
            ->pluck('feed_id')
            ->all();
    }
}
