<?php

namespace App\Services;

use App\Models\File;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Single source of truth for file search. Turns the request's filter params
 * into one Eloquent query that combines free text, date range, size range,
 * type, tag, and folder scope — so the listing and saved searches behave
 * identically everywhere.
 */
class FileSearch
{
    /** Build the filtered file query for the given owner. */
    public function build(Request $request, int $userId, Collection $allFolders): Builder
    {
        $q = File::query()->where('owner_id', $userId)->where('is_dir', false);

        // Free text spans the file name and its extracted metadata/snippet.
        if ($request->filled('search')) {
            $term = $request->string('search')->toString();
            $q->where(function ($w) use ($term) {
                $w->where('name', 'like', "%{$term}%")
                    ->orWhere('metadata', 'like', "%{$term}%");
            });
        }

        // Date range targets either upload time or last in-app content edit.
        $dateCol = $request->string('date_target')->toString() === 'edited'
            ? 'content_edited_at'
            : 'created_at';
        if ($request->filled('date_from')) {
            $q->whereDate($dateCol, '>=', $request->date('date_from'));
        }
        if ($request->filled('date_to')) {
            $q->whereDate($dateCol, '<=', $request->date('date_to'));
        }

        // Size range is in bytes (the client converts from its unit picker).
        if ($request->filled('size_min')) {
            $q->where('size', '>=', $request->integer('size_min'));
        }
        if ($request->filled('size_max')) {
            $q->where('size', '<=', $request->integer('size_max'));
        }

        if ($request->filled('tag')) {
            $tagId = $request->integer('tag');
            $q->whereHas('tags', fn ($t) => $t->where('tags.id', $tagId));
        }

        $ftype = $request->string('ftype')->toString();
        $categories = config('file_categories', []);
        if ($ftype !== '' && isset($categories[$ftype])) {
            $rule = $categories[$ftype];
            if (isset($rule['mime'])) {
                $q->where('mime', 'like', $rule['mime']);
            } else {
                $q->where(function ($sub) use ($rule) {
                    foreach ($rule['ext'] as $ext) {
                        $sub->orWhere('name', 'like', "%.{$ext}");
                    }
                });
            }
        }

        $scopeFolderId = $this->scopeFolderId($request);
        if ($scopeFolderId !== null) {
            $q->whereIn('parent_id', $this->subtreeFolderIds($scopeFolderId, $allFolders));
        }

        return $q;
    }

    /** Any structured filter beyond plain text is "advanced". */
    public function isAdvanced(Request $request): bool
    {
        return $request->hasAny(['date_from', 'date_to', 'size_min', 'size_max', 'ftype']);
    }

    /** True when any search/filter input is present at all. */
    public function isActive(Request $request): bool
    {
        return $request->filled('search') || $this->isAdvanced($request) || $request->filled('tag');
    }

    /** The folder to scope to (and its subtree), or null for all folders. */
    public function scopeFolderId(Request $request): ?int
    {
        return $request->input('scope') === 'folder'
            ? ($request->integer('folder') ?: null)
            : null;
    }

    /** A folder id plus all of its descendant folder ids. */
    public function subtreeFolderIds(int $folderId, Collection $allFolders): array
    {
        $childrenByParent = $allFolders->groupBy('parent_id');
        $ids = [$folderId];
        $stack = [$folderId];
        while ($stack) {
            $parent = array_pop($stack);
            foreach ($childrenByParent->get($parent, collect()) as $child) {
                $ids[] = $child->id;
                $stack[] = $child->id;
            }
        }

        return $ids;
    }
}
