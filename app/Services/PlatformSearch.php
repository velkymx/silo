<?php

namespace App\Services;

use App\Models\Bookmark;
use App\Models\File;
use App\Models\RssItem;
use Illuminate\Support\Collection;

/**
 * Cross-content-type search facade. Every indexed model in the app
 * (File, RssItem, Bookmark) uses Scout's Searchable trait, so this
 * service just fans the query out to each model, scopes by owner,
 * and returns a merged result set grouped by type.
 *
 * The shape is intentionally minimal — the page does its own display
 * grouping, so the service only emits the model, type label, title,
 * snippet, and a click-through URL. The model is hydrated so the
 * page can resolve the full canonical URL without re-querying.
 */
class PlatformSearch
{
    public const PER_TYPE = 20;

    /**
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function search(int $userId, string $query, int $perType = self::PER_TYPE): array
    {
        $query = trim($query);
        if ($query === '') {
            return ['files' => [], 'rss' => [], 'bookmarks' => []];
        }

        $files = File::search($query)
            ->where('owner_id', $userId)
            ->take($perType)
            ->get()
            ->filter(fn (File $f) => ! $f->trashed() && ! $f->is_dir)
            ->map(fn (File $f) => [
                'id' => $f->id,
                'title' => $f->name,
                'snippet' => $f->description ?? null,
                'url' => "/?folder={$f->parent_id}&selected={$f->id}",
                'meta' => ['size' => $f->size, 'folder' => $f->parent_id],
            ])
            ->values()
            ->all();

        $rss = RssItem::search($query)
            ->where('user_id', $userId)
            ->take($perType)
            ->get()
            ->map(fn (RssItem $i) => [
                'id' => $i->id,
                'title' => $i->title,
                'snippet' => $i->excerpt,
                'url' => "/rss/items/{$i->id}",
                'meta' => [
                    'feed_title' => $i->feed_title,
                    'author' => $i->author,
                    'published_at' => optional($i->published_at)->toIso8601String(),
                ],
            ])
            ->values()
            ->all();

        $bookmarks = Bookmark::search($query)
            ->where('owner_id', $userId)
            ->take($perType)
            ->get()
            ->map(fn (Bookmark $b) => [
                'id' => $b->id,
                'title' => $b->title,
                'snippet' => $b->description,
                'url' => $b->url,
                'meta' => ['site' => $b->site_name],
            ])
            ->values()
            ->all();

        return [
            'files' => $files,
            'rss' => $rss,
            'bookmarks' => $bookmarks,
        ];
    }
}
