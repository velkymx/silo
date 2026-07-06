<?php

namespace App\Http\Controllers\Rss;

use App\Http\Controllers\Controller;
use App\Jobs\Rss\RefreshFeed;
use App\Models\RssFeed;
use App\Models\RssItem;
use App\Models\Setting;
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

        $feeds = RssFeed::ownedBy($userId)
            ->orderBy('folder')->orderBy('sort_order')->orderBy('title')
            ->get(['id', 'title', 'folder', 'enabled', 'refresh_interval_minutes', 'last_fetched_at', 'last_error']);

        $unreadByFeed = RssItem::ownedBy($userId)->unread()
            ->selectRaw('feed_id, count(*) as c')
            ->groupBy('feed_id')
            ->pluck('c', 'feed_id');

        $feeds = $feeds->map(function (RssFeed $feed) use ($unreadByFeed) {
            return [
                'id' => $feed->id,
                'title' => $feed->title,
                'folder' => $feed->folder,
                'enabled' => $feed->enabled,
                'refresh_interval_minutes' => (int) $feed->refresh_interval_minutes,
                'last_fetched_at' => optional($feed->last_fetched_at)->toIso8601String(),
                'last_error' => $feed->last_error,
                'unread_count' => (int) ($unreadByFeed[$feed->id] ?? 0),
            ];
        })->values();

        $items = RssItem::with('feed:id,title,folder')
            ->ownedBy($userId)
            ->when($filter === 'starred', fn ($q) => $q->starred())
            ->when($filter === 'unread', fn ($q) => $q->unread())
            ->when($feedId > 0, fn ($q) => $q->forFeed($feedId))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('title', 'like', "%{$search}%")
                        ->orWhere('excerpt', 'like', "%{$search}%")
                        ->orWhere('author', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('published_at')
            ->limit(200)
            ->get()
            ->map(fn (RssItem $i) => $this->shapeItem($i))
            ->values();

        $unreadTotal = RssItem::ownedBy($userId)->unread()->count();
        $starredTotal = RssItem::ownedBy($userId)->starred()->count();
        $automationEnabled = (bool) Setting::get('rss.automation_enabled', true);

        return Inertia::render('Rss/Index', [
            'feeds' => $feeds,
            'items' => $items,
            'filters' => [
                'filter' => $filter ?: null,
                'feed' => $feedId ?: null,
                'search' => $search ?: null,
            ],
            'counts' => [
                'unread' => $unreadTotal,
                'starred' => $starredTotal,
                'feeds' => $feeds->count(),
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

    public function refresh(RssFeed $feed)
    {
        $this->authorize('update', $feed);
        RefreshFeed::dispatch($feed->id);

        return back()->with('success', 'Refresh queued.');
    }

    public function refreshAll()
    {
        $count = RssFeed::ownedBy(auth()->id())->where('enabled', true)->count();
        RssFeed::ownedBy(auth()->id())->where('enabled', true)
            ->orderBy('id')->each(fn (RssFeed $f) => RefreshFeed::dispatch($f->id));

        return back()->with('success', "Queued {$count} feed(s) for refresh.");
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
            'url' => $item->url,
            'published_at' => optional($item->published_at)->toIso8601String(),
            'is_read' => (bool) $item->is_read,
            'is_starred' => (bool) $item->is_starred,
        ];
    }
}
