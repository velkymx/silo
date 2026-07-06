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
use App\Models\Setting;
use App\Services\Audit;
use App\Services\Rss\FeedDiscovery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

        $feedsQuery = RssFeed::ownedBy($userId)
            ->when(! $showMuted, fn ($q) => $q->unmuted())
            ->orderBy('folder')->orderBy('sort_order')->orderBy('title');

        $feeds = $feedsQuery->get(['id', 'title', 'folder', 'enabled', 'muted_at', 'refresh_interval_minutes', 'last_fetched_at', 'last_error']);

        $unreadByFeed = RssItem::ownedBy($userId)
            ->whereHas('feed', fn ($q) => $q->where('user_id', $userId))
            ->unread()
            ->selectRaw('feed_id, count(*) as c')
            ->groupBy('feed_id')
            ->pluck('c', 'feed_id');

        $feeds = $feeds->map(function (RssFeed $feed) use ($unreadByFeed) {
            return (new RssFeedResource($feed))->toArray(request()) + [
                'unread_count' => (int) ($unreadByFeed[$feed->id] ?? 0),
            ];
        })->values();

        $items = RssItem::with('feed:id,title,folder,muted_at')
            ->ownedBy($userId)
            ->whereHas('feed', fn ($q) => $q->where('user_id', $userId)->unmuted())
            ->when($filter === 'starred', fn ($q) => $q->starred())
            ->when($filter === 'unread', fn ($q) => $q->unread())
            ->when($filter === 'today', fn ($q) => $q->where('published_at', '>=', now()->startOfDay()))
            ->when($filter === 'week', fn ($q) => $q->where('published_at', '>=', now()->subDays(7)))
            ->when($filter === 'recent', fn ($q) => $q->where('created_at', '>=', now()->subDays(7)))
            ->when($feedId > 0, fn ($q) => $q->forFeed($feedId))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('title', 'like', "%{$search}%")
                        ->orWhere('excerpt', 'like', "%{$search}%")
                        ->orWhere('author', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->cursorPaginate(50);

        $itemRows = collect($items->items())
            ->map(fn (RssItem $i) => (new RssItemResource($i))->toArray(request()))
            ->values();
        $itemsNextCursor = $items->nextCursor()?->encode();

        $unreadTotal = RssItem::ownedBy($userId)
            ->whereHas('feed', fn ($q) => $q->where('user_id', $userId)->unmuted())
            ->unread()
            ->count();
        $starredTotal = RssItem::ownedBy($userId)->starred()->count();
        $todayCount = RssItem::ownedBy($userId)
            ->whereHas('feed', fn ($q) => $q->where('user_id', $userId)->unmuted())
            ->where('published_at', '>=', now()->startOfDay())
            ->count();
        $weekCount = RssItem::ownedBy($userId)
            ->whereHas('feed', fn ($q) => $q->where('user_id', $userId)->unmuted())
            ->where('published_at', '>=', now()->subDays(7))
            ->count();
        $recentCount = RssItem::ownedBy($userId)
            ->whereHas('feed', fn ($q) => $q->where('user_id', $userId)->unmuted())
            ->where('created_at', '>=', now()->subDays(7))
            ->count();
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
                'week' => $weekCount,
                'recent' => $recentCount,
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
        $mime = match ($ext) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            default => 'image/x-icon',
        };

        return response($bytes, 200, [
            'Content-Type' => $mime,
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
        foreach ($feeds as $feed) {
            $first = RssItem::where('feed_id', $feed->id)->min('published_at');
            $last = RssItem::where('feed_id', $feed->id)->max('published_at');
            $count = RssItem::where('feed_id', $feed->id)->count();
            if (! $first || ! $last || $count < 2) {
                continue;
            }
            $firstAt = \Carbon\Carbon::parse($first);
            $lastAt = \Carbon\Carbon::parse($last);
            $spanHours = max(1, $firstAt->diffInHours($lastAt));
            $sum += $spanHours / ($count - 1);
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

        $perFeed = $feeds->map(fn (RssFeed $f) => [
            'id' => $f->id,
            'title' => $f->title,
            'folder' => $f->folder,
            'count' => (int) ($articlesPerFeed[$f->id] ?? 0),
            'last_fetched_at' => optional($f->last_fetched_at)->toIso8601String(),
            'last_success_at' => optional($f->last_success_at)->toIso8601String(),
            'last_error' => $f->last_error,
        ])->values();

        return response()->json([
            'articles_today' => $articlesToday,
            'last_success_at' => optional($lastSuccessAt)->toIso8601String(),
            'avg_frequency_hours' => $avgFrequencyHours,
            'success_rate' => $successRate,
            'failed_count' => $failedCount,
            'unread_total' => $unreadTotal,
            'feeds_count' => $feeds->count(),
            'per_feed' => $perFeed,
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
