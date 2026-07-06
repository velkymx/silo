<?php

namespace App\Http\Controllers\Rss;

use App\Http\Controllers\Controller;
use App\Jobs\Rss\RefreshFeed;
use App\Models\RssFeed;
use App\Models\RssItem;
use App\Models\Setting;
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
            return [
                'id' => $feed->id,
                'title' => $feed->title,
                'folder' => $feed->folder,
                'enabled' => $feed->enabled,
                'muted' => $feed->isMuted(),
                'muted_at' => optional($feed->muted_at)->toIso8601String(),
                'refresh_interval_minutes' => (int) $feed->refresh_interval_minutes,
                'last_fetched_at' => optional($feed->last_fetched_at)->toIso8601String(),
                'last_error' => $feed->last_error,
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
            ->map(fn (RssItem $i) => $this->shapeItem($i))
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

    public function store(Request $request)
    {
        Gate::authorize('create', RssFeed::class);
        $data = $this->validateFeed($request);
        $feed = RssFeed::create($data + ['user_id' => auth()->id()]);
        RefreshFeed::dispatch($feed->id);

        return back()->with('success', 'Feed added. Refreshing in the background…');
    }

    public function update(Request $request, RssFeed $feed)
    {
        $this->authorize('update', $feed);
        $data = $this->validateFeed($request, partial: true);
        $feed->update($data);

        return back()->with('success', 'Feed updated.');
    }

    public function destroy(RssFeed $feed)
    {
        $this->authorize('delete', $feed);
        $feed->delete();

        return back()->with('success', 'Feed removed.');
    }

    /**
     * Best-effort feed discovery. Given a page URL, fetch its HTML and
     * look for the first <link rel="alternate" type="application/rss+xml">
     * (or atom). Returns the resolved feed URL, or 422 if nothing found.
     */
    public function discover(Request $request, FeedDiscovery $discovery): JsonResponse
    {
        $data = $request->validate([
            'url' => ['required', 'string', 'url', 'max:2048'],
        ]);

        $found = $discovery->discover($data['url']);
        if (! $found) {
            return response()->json([
                'message' => 'No feed link found on that page.',
            ], 422);
        }

        return response()->json([
            'url' => $found->url,
            'title' => $found->title,
            'source' => $data['url'],
        ]);
    }

    public function refresh(RssFeed $feed)
    {
        $this->authorize('update', $feed);
        if ($feed->isMuted()) {
            return back()->with('error', 'Feed is muted; unmute it to refresh.');
        }
        RefreshFeed::dispatch($feed->id);

        return back()->with('success', 'Refresh queued.');
    }

    public function refreshAll()
    {
        $query = RssFeed::ownedBy(auth()->id())->where('enabled', true)->unmuted();
        $count = (clone $query)->count();
        $query->orderBy('id')->each(fn (RssFeed $f) => RefreshFeed::dispatch($f->id));

        return back()->with('success', "Queued {$count} feed(s) for refresh.");
    }

    public function mute(RssFeed $feed)
    {
        $this->authorize('mute', $feed);
        $feed->mute();

        return back()->with('success', "“{$feed->title}” muted. It is hidden from the inbox and skipped on refresh.");
    }

    public function unmute(RssFeed $feed)
    {
        $this->authorize('mute', $feed);
        $feed->unmute();

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
    private function validateFeed(Request $request, bool $partial = false): array
    {
        $required = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'title' => $required.'|string|max:120',
            'url' => $required.'|url|max:2048',
            'folder' => 'nullable|string|max:60',
            'enabled' => 'boolean',
            'refresh_interval_minutes' => 'nullable|integer|min:5|max:1440',
        ]) + ($partial ? [] : ['enabled' => $request->boolean('enabled', true), 'refresh_interval_minutes' => (int) $request->input('refresh_interval_minutes', 60)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function shapeItem(RssItem $item): array
    {
        return [
            'id' => $item->id,
            'feed_id' => $item->feed_id,
            'feed_title' => $item->feed?->title,
            'feed_folder' => $item->feed?->folder,
            'guid' => $item->guid,
            'title' => $item->title,
            'excerpt' => $item->excerpt,
            'author' => $item->author,
            'categories' => $item->categories ?? [],
            'image_url' => $item->image_url,
            'url' => $item->url,
            'published_at' => optional($item->published_at)->toIso8601String(),
            'is_read' => (bool) $item->is_read,
            'is_starred' => (bool) $item->is_starred,
        ];
    }
}
