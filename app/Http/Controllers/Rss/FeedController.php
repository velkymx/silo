<?php

namespace App\Http\Controllers\Rss;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rss\DiscoverFeedRequest;
use App\Http\Requests\Rss\StoreFeedRequest;
use App\Http\Requests\Rss\UpdateFeedRequest;
use App\Http\Resources\Rss\Feed as RssFeedResource;
use App\Http\Resources\Rss\Item as RssItemResource;
use App\Jobs\Rss\RefreshFeed;
use App\Models\RssFeed;
use App\Models\RssItem;
use App\Models\RssRefreshLog;
use App\Models\Setting;
use App\Services\Audit;
use App\Services\Rss\FeedDiscovery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * RSS surfaces. The Inbox is just the unread-items query view (no inbox
 * table) so the controller's job is to shape the data and hand it to the
 * Inertia page; mutation endpoints are narrow and policy-gated.
 */
class FeedController extends Controller
{
    public function index(Request $request)
    {
        $userId = auth()->id();
        $filter = $request->string('filter')->lower()->toString();
        $search = trim((string) $request->string('search'));
        $feedId = $request->integer('feed');
        $showMuted = $request->boolean('show_muted');
        $blocked = $request->user()?->blocked_keywords ?? [];

        $feedsQuery = RssFeed::ownedBy($userId)
            ->when(! $showMuted, fn ($q) => $q->unmuted());

        $feeds = $feedsQuery->get(['id', 'title', 'folder', 'enabled', 'muted_at', 'favicon_path', 'refresh_interval_minutes', 'last_fetched_at', 'last_success_at', 'last_error', 'last_http_status', 'last_response_time_ms', 'consecutive_failures', 'etag', 'last_modified']);

        $unreadByFeed = RssItem::ownedBy($userId)
            ->unread()
            ->selectRaw('feed_id, count(*) as c')
            ->groupBy('feed_id')
            ->pluck('c', 'feed_id');

        $feeds = $feeds->map(function (RssFeed $feed) use ($unreadByFeed) {
            return (new RssFeedResource($feed))->toArray(request()) + [
                'unread_count' => (int) ($unreadByFeed[$feed->id] ?? 0),
            ];
        })->values();

        $author = trim((string) $request->string('author')->toString());
        $exclude = trim((string) $request->string('exclude')->toString());

        $topFeedIds = $filter === 'top_feeds'
            ? RssItem::ownedBy($userId)
                ->whereIn('feed_id', RssFeed::ownedBy($userId)->whereNull('muted_at')->pluck('id'))
                ->where('published_at', '>=', now()->subDays(7))
                ->selectRaw('feed_id, count(*) as c')
                ->groupBy('feed_id')
                ->orderByDesc('c')
                ->limit(5)
                ->pluck('feed_id')
                ->all()
            : [];

        $items = RssItem::with('feed:id,title,folder,muted_at')
            ->ownedBy($userId)
            ->whereHas('feed', fn ($q) => $q->unmuted())
            ->inboxFilter($filter, $feedId, $search, $author, $exclude)
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
            })
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->cursorPaginate(50);

        $itemRows = collect($items->items())
            ->map(fn (RssItem $i) => (new RssItemResource($i))->toArray(request()))
            ->values();
        $itemsNextCursor = $items->nextCursor()?->encode();

        // Sidebar counters in one conditional-aggregate pass over the user's
        // unmuted items. rss_items.user_id already scopes ownership, so the
        // unmuted-feed constraint is expressed with a single id whitelist
        // instead of a per-count whereHas join.
        $startOfDay = now()->startOfDay();
        $yesterdayStart = now()->subDay()->startOfDay();
        $yesterdayEnd = now()->subDay()->endOfDay();
        $weekAgo = now()->subDays(7);
        $monthAgo = now()->subDays(30);
        $unmutedFeedIds = RssFeed::ownedBy($userId)->unmuted()->pluck('id');

        $agg = RssItem::ownedBy($userId)
            ->whereIn('feed_id', $unmutedFeedIds)
            ->selectRaw(
                'SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread,'
                .' SUM(CASE WHEN published_at >= ? THEN 1 ELSE 0 END) as today,'
                .' SUM(CASE WHEN published_at BETWEEN ? AND ? THEN 1 ELSE 0 END) as yesterday,'
                .' SUM(CASE WHEN published_at >= ? THEN 1 ELSE 0 END) as week,'
                .' SUM(CASE WHEN published_at >= ? THEN 1 ELSE 0 END) as month,'
                .' SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) as recent,'
                .' SUM(CASE WHEN is_read = 1 AND read_at >= ? THEN 1 ELSE 0 END) as read_recent',
                [$startOfDay, $yesterdayStart, $yesterdayEnd, $weekAgo, $monthAgo, $weekAgo, $weekAgo]
            )
            ->first();

        $unreadTotal = (int) ($agg->unread ?? 0);
        $todayCount = (int) ($agg->today ?? 0);
        $yesterdayCount = (int) ($agg->yesterday ?? 0);
        $weekCount = (int) ($agg->week ?? 0);
        $monthCount = (int) ($agg->month ?? 0);
        $recentCount = (int) ($agg->recent ?? 0);
        $readRecentCount = (int) ($agg->read_recent ?? 0);
        $starredTotal = RssItem::ownedBy($userId)->starred()->count();
        $mutedCount = RssFeed::ownedBy($userId)->muted()->count();
        $automationEnabled = (bool) Setting::get('rss.automation_enabled', true);

        return Inertia::render('Rss/Index', [
            'feeds' => $feeds,
            'items' => $itemRows,
            'itemsNextCursor' => $itemsNextCursor,
            'filters' => [
                'filter' => $filter ?: null,
                'feed' => $feedId ?: null,
                'search' => $search ?: null,
                'show_muted' => $showMuted,
            ],
            'counts' => [
                'unread' => $unreadTotal,
                'starred' => $starredTotal,
                'today' => $todayCount,
                'yesterday' => $yesterdayCount,
                'week' => $weekCount,
                'month' => $monthCount,
                'recent' => $recentCount,
                'read_recent' => $readRecentCount,
                'feeds' => $feeds->count(),
                'muted' => $mutedCount,
            ],
            'automationEnabled' => $automationEnabled,
        ]);
    }

    public function store(StoreFeedRequest $request)
    {
        $feed = RssFeed::create($request->validatedFeedAttributes() + ['user_id' => auth()->id()]);
        RefreshFeed::dispatch($feed->id);
        Audit::log('rss.feed.create', null, ['feed_id' => $feed->id, 'url' => $feed->url], subjectName: $feed->title);

        return back()->with('success', 'Feed added. Refreshing in the background…');
    }

    public function update(UpdateFeedRequest $request, RssFeed $feed)
    {
        $feed->update($request->validated());

        return back()->with('success', 'Feed updated.');
    }

    public function destroy(RssFeed $feed)
    {
        $this->authorize('delete', $feed);
        $title = $feed->title;
        $id = $feed->id;
        $feed->delete();
        Audit::log('rss.feed.delete', null, ['feed_id' => $id], subjectName: $title);

        return back()->with('success', 'Feed removed.');
    }

    /**
     * Best-effort feed discovery. Given a page URL, fetch its HTML and
     * look for the first <link rel="alternate" type="application/rss+xml">
     * (or atom). Returns the resolved feed URL, or 422 if nothing found.
     */
    public function discover(DiscoverFeedRequest $request, FeedDiscovery $discovery): JsonResponse
    {
        $found = $discovery->discover($request->string('url')->toString());
        if (! $found) {
            return response()->json([
                'message' => 'No feed link found on that page.',
            ], 422);
        }

        return response()->json([
            'url' => $found->url,
            'title' => $found->title,
            'source' => $request->string('url')->toString(),
        ]);
    }

    public function refresh(RssFeed $feed)
    {
        $this->authorize('update', $feed);
        if ($feed->isMuted()) {
            return back()->with('error', 'Feed is muted; unmute it to refresh.');
        }
        RefreshFeed::dispatch($feed->id);
        Audit::log('rss.feed.refresh', null, ['feed_id' => $feed->id], subjectName: $feed->title);

        return back()->with('success', 'Refresh queued.');
    }

    public function refreshAll()
    {
        $query = RssFeed::ownedBy(auth()->id())->where('enabled', true)->unmuted();
        $count = (clone $query)->count();
        $query->orderBy('id')->each(fn (RssFeed $f) => RefreshFeed::dispatch($f->id));
        if ($count > 0) {
            Audit::log('rss.feed.refresh_all', null, ['count' => $count]);
        }

        return back()->with('success', "Queued {$count} feed(s) for refresh.");
    }

    public function mute(RssFeed $feed)
    {
        $this->authorize('mute', $feed);
        $feed->mute();
        Audit::log('rss.feed.mute', null, ['feed_id' => $feed->id], subjectName: $feed->title);

        return back()->with('success', "“{$feed->title}” muted. It is hidden from the inbox and skipped on refresh.");
    }

    /**
     * Stream the cached favicon for a feed, or 404. The disk path is
     * opaque — the route is policy-gated by RssFeedPolicy::view, so a
     * muted feed's icon is still fetchable but only for its owner.
     */
    public function favicon(RssFeed $feed)
    {
        $this->authorize('view', $feed);
        if (! $feed->favicon_path || ! \Storage::disk('local')->exists($feed->favicon_path)) {
            abort(404);
        }
        $bytes = \Storage::disk('local')->get($feed->favicon_path);
        $ext = strtolower(pathinfo($feed->favicon_path, PATHINFO_EXTENSION));
        // No image/svg+xml: the fetcher refuses SVG, but any legacy .svg on
        // disk is served as a raster type + nosniff so it can never execute.
        $mime = match ($ext) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            default => 'image/x-icon',
        };

        return response($bytes, 200, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
            'Cache-Control' => 'public, max-age=86400',
        ]);
    }

    public function unmute(RssFeed $feed)
    {
        $this->authorize('mute', $feed);
        $feed->unmute();
        Audit::log('rss.feed.unmute', null, ['feed_id' => $feed->id], subjectName: $feed->title);

        return back()->with('success', "“{$feed->title}” unmuted.");
    }

    /**
     * Aggregate inbox metrics for the stats panel: today's intake, last
     * successful fetch, average publish frequency, success rate over
     * the last 30 days, failed feeds, and items-per-feed.
     */
    public function stats(Request $request): \Illuminate\Http\JsonResponse
    {
        $userId = auth()->id();
        $feeds = RssFeed::ownedBy($userId)->whereNull('muted_at')->get();
        $feedIds = $feeds->pluck('id');

        $articlesToday = RssItem::ownedBy($userId)
            ->whereIn('feed_id', $feedIds)
            ->where('created_at', '>=', now()->startOfDay())
            ->count();

        $lastSuccessAt = $feeds->whereNotNull('last_success_at')->max('last_success_at');

        // Average publish frequency in hours: mean of (range / items) for
        // feeds with >= 2 items, computed from the actual published_at span
        // so a one-time scrape doesn't skew the average to infinity.
        $avgFrequencyHours = null;
        $samples = 0;
        $sum = 0.0;
        $spans = RssItem::whereIn('feed_id', $feedIds)
            ->selectRaw('feed_id, MIN(published_at) as first_at, MAX(published_at) as last_at, COUNT(*) as c')
            ->groupBy('feed_id')
            ->get();
        foreach ($spans as $span) {
            if (! $span->first_at || ! $span->last_at || (int) $span->c < 2) {
                continue;
            }
            $firstAt = \Carbon\Carbon::parse($span->first_at);
            $lastAt = \Carbon\Carbon::parse($span->last_at);
            $spanHours = max(1, $firstAt->diffInHours($lastAt));
            $sum += $spanHours / ((int) $span->c - 1);
            $samples++;
        }
        if ($samples > 0) {
            $avgFrequencyHours = (int) round($sum / $samples);
        }

        // Success rate over the last 30 days: feeds with last_error set vs
        // total enabled feeds. A 30-day sliding window is too short to
        // compute from per-fetch logs we don't keep, so this is the
        // current health snapshot (last fetch result).
        $enabledFeeds = $feeds->where('enabled', true);
        $totalEnabled = $enabledFeeds->count();
        $failedCount = $enabledFeeds->whereNotNull('last_error')->count();
        $successRate = $totalEnabled > 0
            ? round((($totalEnabled - $failedCount) / $totalEnabled) * 100)
            : null;

        $unreadTotal = RssItem::ownedBy($userId)
            ->whereIn('feed_id', $feedIds)
            ->unread()
            ->count();

        $articlesPerFeed = RssItem::ownedBy($userId)
            ->whereIn('feed_id', $feedIds)
            ->selectRaw('feed_id, count(*) as c')
            ->groupBy('feed_id')
            ->pluck('c', 'feed_id');

        $articlesLast24h = RssItem::ownedBy($userId)
            ->whereIn('feed_id', $feedIds)
            ->where('created_at', '>=', now()->subDay())
            ->count();
        $articlesLastWeek = RssItem::ownedBy($userId)
            ->whereIn('feed_id', $feedIds)
            ->where('created_at', '>=', now()->subDays(7))
            ->count();
        $articlesPerDay = $articlesLastWeek > 0 ? round($articlesLastWeek / 7, 1) : 0;
        $articlesPerWeek = $articlesLastWeek;

        $perFeed = $feeds->map(fn (RssFeed $f) => [
            'id' => $f->id,
            'title' => $f->title,
            'folder' => $f->folder,
            'count' => (int) ($articlesPerFeed[$f->id] ?? 0),
            'last_fetched_at' => optional($f->last_fetched_at)->toIso8601String(),
            'last_success_at' => optional($f->last_success_at)->toIso8601String(),
            'last_error' => $f->last_error,
            'last_http_status' => $f->last_http_status,
            'last_response_time_ms' => $f->last_response_time_ms,
            'consecutive_failures' => $f->consecutive_failures,
        ])->values()->sortByDesc('last_success_at')->values();

        // Historical analytics from the rss_refresh_logs append-only log.
        $refreshHistory = RssRefreshLog::query()
            ->whereIn('rss_feed_id', $feeds->pluck('id'))
            ->where('started_at', '>=', now()->subDays(30))
            ->orderByDesc('started_at')
            ->limit(20)
            ->get(['rss_feed_id', 'started_at', 'completed_at', 'http_status', 'response_time_ms', 'outcome', 'new_items_count', 'error'])
            ->map(fn (RssRefreshLog $l) => [
                'feed_id' => $l->rss_feed_id,
                'started_at' => $l->started_at?->toIso8601String(),
                'completed_at' => $l->completed_at?->toIso8601String(),
                'http_status' => $l->http_status,
                'response_time_ms' => $l->response_time_ms,
                'outcome' => $l->outcome,
                'outcome_label' => $l->outcomeLabel(),
                'new_items_count' => $l->new_items_count,
                'error' => $l->error,
            ])
            ->all();

        // Longest outage: the longest run of consecutive non-success attempts
        // across all feeds in the last 30 days. A run is bounded by the
        // next success or the window edge.
        $logs = RssRefreshLog::query()
            ->whereIn('rss_feed_id', $feeds->pluck('id'))
            ->where('started_at', '>=', now()->subDays(30))
            ->orderBy('started_at')
            ->get(['started_at', 'outcome']);
        $longestOutageMinutes = 0;
        $outageStart = null;
        foreach ($logs as $log) {
            if ($log->outcome !== RssRefreshLog::OUTCOME_SUCCESS) {
                $outageStart ??= $log->started_at;
            } else {
                if ($outageStart) {
                    $longestOutageMinutes = max($longestOutageMinutes, $outageStart->diffInMinutes($log->started_at));
                    $outageStart = null;
                }
            }
        }
        if ($outageStart) {
            $longestOutageMinutes = max($longestOutageMinutes, $outageStart->diffInMinutes(now()));
        }

        // Items per day, last 30 days, bucketed per day.
        $itemsPerDay = RssItem::ownedBy($userId)
            ->whereIn('feed_id', $feedIds)
            ->where('created_at', '>=', now()->subDays(30))
            ->select(DB::raw('DATE(created_at) as d'), DB::raw('count(*) as c'))
            ->groupBy('d')
            ->orderBy('d')
            ->pluck('c', 'd')
            ->all();

        return response()->json([
            'articles_today' => $articlesToday,
            'articles_per_day' => $articlesPerDay,
            'articles_per_week' => $articlesPerWeek,
            'last_success_at' => optional($lastSuccessAt)->toIso8601String(),
            'avg_frequency_hours' => $avgFrequencyHours,
            'success_rate' => $successRate,
            'failed_count' => $failedCount,
            'unread_total' => $unreadTotal,
            'feeds_count' => $feeds->count(),
            'per_feed' => $perFeed,
            'refresh_history' => $refreshHistory,
            'longest_outage_minutes' => $longestOutageMinutes,
            'items_per_day' => $itemsPerDay,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function shapeItem(RssItem $item): array
    {
        return (new RssItemResource($item))->toArray(request());
    }
}
