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

        // The full results page keeps notes inside the files group (a note is a
        // File), so it uses filesAll() rather than the notes-split files().
        return [
            'files' => $this->filesAll($userId, $query, $perType),
            'rss' => $this->rss($userId, $query, $perType),
            'bookmarks' => $this->bookmarks($userId, $query, $perType),
        ];
    }

    /**
     * Grouped results for the quick-search palette. `scope` picks which groups
     * to run: a single surface, or `all`. Notes are split out of files here so
     * the palette can group them separately.
     *
     * @return array<string, array<int, array<string, mixed>>>
     */
    public function quick(int $userId, string $query, string $scope = 'all', int $perType = 5): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        $groups = match ($scope) {
            'files' => ['files'],
            'notes' => ['notes'],
            'rss' => ['rss'],
            'bookmarks' => ['bookmarks'],
            default => ['files', 'notes', 'rss', 'bookmarks'],
        };

        $results = [];
        foreach ($groups as $group) {
            $results[$group] = $this->{$group}($userId, $query, $perType);
        }

        return $results;
    }

    /**
     * Files excluding notes (markdown) — the palette's "files" group.
     *
     * @return array<int, array<string, mixed>>
     */
    public function files(int $userId, string $query, int $perType = self::PER_TYPE): array
    {
        return File::search($query)
            ->where('owner_id', $userId)
            ->take($perType)
            ->get()
            ->filter(fn (File $f) => ! $f->trashed() && ! $f->is_dir && $f->mime !== 'text/markdown')
            ->map(fn (File $f) => $this->mapFile($f))
            ->values()
            ->all();
    }

    /**
     * Notes (markdown files) — the palette's "notes" group; opens on /notes.
     *
     * @return array<int, array<string, mixed>>
     */
    public function notes(int $userId, string $query, int $perType = self::PER_TYPE): array
    {
        return File::search($query)
            ->where('owner_id', $userId)
            ->take($perType)
            ->get()
            ->filter(fn (File $f) => ! $f->trashed() && ! $f->is_dir && $f->mime === 'text/markdown')
            ->map(fn (File $f) => [
                'id' => $f->id,
                'title' => $f->name,
                'snippet' => null,
                'url' => route('notes.index', ['open' => $f->id]),
                'meta' => ['folder' => $f->parent_id],
            ])
            ->values()
            ->all();
    }

    /**
     * All files including notes — the full results page's "files" group.
     *
     * @return array<int, array<string, mixed>>
     */
    private function filesAll(int $userId, string $query, int $perType): array
    {
        return File::search($query)
            ->where('owner_id', $userId)
            ->take($perType)
            ->get()
            ->filter(fn (File $f) => ! $f->trashed() && ! $f->is_dir)
            ->map(fn (File $f) => $this->mapFile($f))
            ->values()
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function rss(int $userId, string $query, int $perType = self::PER_TYPE): array
    {
        return RssItem::search($query)
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
    }

    /** @return array<int, array<string, mixed>> */
    public function bookmarks(int $userId, string $query, int $perType = self::PER_TYPE): array
    {
        return Bookmark::search($query)
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
    }

    /** @return array<string, mixed> */
    private function mapFile(File $f): array
    {
        return [
            'id' => $f->id,
            'title' => $f->name,
            'snippet' => $f->description ?? null,
            'url' => "/?folder={$f->parent_id}&selected={$f->id}",
            'meta' => ['size' => $f->size, 'folder' => $f->parent_id],
        ];
    }
}
