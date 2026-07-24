<?php

namespace App\Http\Resources\Rss;

use App\Models\RssItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Canonical wire shape for an RssItem across every surface that emits
 * one (inbox sidebar counts, Rss/Index row, Rss/Show full view, and
 * the /starred cross-content-type view).
 *
 * Centralises the field list so a schema change in one place updates
 * every consumer. The shape covers both the list view (used in
 * FeedController::index) and the full view (used in
 * ItemController::show) — pass `full: true` from toArray() callers
 * that need the article body.
 */
class Item extends JsonResource
{
    /**
     * @var bool  include the full HTML body (used by Rss/Show only)
     */
    public bool $full = false;

    public function withFull(bool $full = true): static
    {
        $this->full = $full;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var RssItem $item */
        $item = $this->resource;

        $base = [
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

        if ($this->full) {
            $base['content'] = $item->content;
            $base['feed_site_url'] = $item->feed?->site_url;
        }

        return $base;
    }
}
