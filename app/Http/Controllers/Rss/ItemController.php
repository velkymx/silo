<?php

namespace App\Http\Controllers\Rss;

use App\Automation\EventDispatcher;
use App\Http\Controllers\Controller;
use App\Models\RssItem;
use App\Services\Audit;
use App\Services\Rss\InboxItemQuery;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ItemController extends Controller
{
    public function show(RssItem $item)
    {
        $this->authorize('view', $item);
        $item->load('feed:id,title,folder,site_url');

        return Inertia::render('Rss/Show', [
            'item' => $this->shapeFull($item),
            'related' => $this->shapeRelated($item),
        ]);
    }

    public function markRead(RssItem $item, Request $request, EventDispatcher $events)
    {
        $this->authorize('update', $item);
        if (! $item->is_read) {
            $item->markRead();
            $events->dispatch('rss.item.read', $item->user_id, [
                'item_id' => $item->id,
                'feed_id' => $item->feed_id,
                'title' => $item->title,
                'url' => $item->url,
                'read_at' => $item->read_at?->toIso8601String(),
            ], "rss.item.read@1:item:{$item->id}:".$item->read_at?->timestamp);
        }

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'is_read' => true]);
        }

        return back();
    }

    public function markUnread(RssItem $item, Request $request)
    {
        $this->authorize('update', $item);
        $item->markUnread();

        if ($request->wantsJson()) {
            return response()->json(['ok' => true, 'is_read' => false]);
        }

        return back();
    }

    public function toggleStar(RssItem $item, EventDispatcher $events)
    {
        $this->authorize('update', $item);
        $starred = $item->toggleStar();
        $events->dispatch('rss.item.starred', $item->user_id, [
            'item_id' => $item->id,
            'feed_id' => $item->feed_id,
            'title' => $item->title,
            'url' => $item->url,
            'starred' => $starred,
            'starred_at' => $item->starred_at?->toIso8601String(),
        ], "rss.item.starred@1:item:{$item->id}:".$item->starred_at?->timestamp);
        Audit::log($starred ? 'rss.item.star' : 'rss.item.unstar', null, ['item_id' => $item->id, 'feed_id' => $item->feed_id], subjectName: $item->title);

        return back();
    }

    public function markAllRead(Request $request, EventDispatcher $events, InboxItemQuery $inbox)
    {
        $userId = auth()->id();

        // Scope to exactly the visible set — the same filter chain the inbox
        // listing uses (smart folder, feed, author/exclude, boolean search,
        // feed combinations, top-feeds, blocked keywords).
        $base = RssItem::ownedBy($userId)
            ->whereHas('feed', fn ($q) => $q->unmuted())
            ->unread();
        $inbox->apply($base, $request, $userId);

        $now = now();
        $count = (clone $base)->update(['is_read' => true, 'read_at' => $now]);

        if ($count > 0) {
            $events->dispatch('rss.item.read', $userId, [
                'phase' => 'bulk',
                'count' => $count,
                'filter' => $request->string('filter')->lower()->toString() ?: null,
                'feed_id' => $request->integer('feed') ?: null,
                'read_at' => $now->toIso8601String(),
            ], 'rss.item.read@1:bulk:'.$userId.':'.$now->timestamp);
        }

        return back()->with('success', "Marked {$count} item(s) as read.");
    }

    /**
     * @return array<string, mixed>
     */
    private function shapeFull(RssItem $item): array
    {
        return [
            'id' => $item->id,
            'feed_id' => $item->feed_id,
            'feed_title' => $item->feed?->title,
            'feed_site_url' => $item->feed?->site_url,
            'guid' => $item->guid,
            'title' => $item->title,
            'content' => $item->content,
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

    /**
     * @return array<int, array<string, mixed>>
     */
    private function shapeRelated(RssItem $item): array
    {
        return RssItem::ownedBy($item->user_id)
            ->where('id', '!=', $item->id)
            ->where('feed_id', $item->feed_id)
            ->orderByDesc('published_at')
            ->limit(10)
            ->get()
            ->map(fn (RssItem $i) => [
                'id' => $i->id,
                'title' => $i->title,
                'published_at' => optional($i->published_at)->toIso8601String(),
                'is_read' => (bool) $i->is_read,
            ])
            ->all();
    }
}
