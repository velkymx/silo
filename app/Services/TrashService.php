<?php

namespace App\Services;

use App\Models\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TrashService
{
    /**
     * Restore a trashed item and everything that was trashed underneath it.
     */
    public function restore(File $file): void
    {
        DB::transaction(function () use ($file) {
            $file->restore();
            foreach ($file->children()->onlyTrashed()->get() as $child) {
                $this->restore($child);
            }
        });
    }

    /**
     * Permanently delete a trashed item, its whole subtree, and all blobs
     * (file bytes, thumbnails, version bytes). DB rows cascade via FKs.
     */
    public function purge(File $file): void
    {
        DB::transaction(function () use ($file) {
            foreach ($this->subtree($file) as $node) {
                $this->forgetBlobs($node);
            }

            $file->forceDelete(); // cascades descendant rows + versions/links/permissions
        });
    }

    /**
     * Permanently delete every item the user trashed more than $days ago.
     */
    public function purgeOlderThan(int $days): int
    {
        $roots = File::onlyTrashed()
            ->where('deleted_at', '<', now()->subDays($days))
            ->get()
            ->filter(fn (File $f) => ! $this->parentIsTrashed($f)); // only deletion roots

        foreach ($roots as $root) {
            $this->purge($root);
        }

        return $roots->count();
    }

    /**
     * The deletion roots in a user's trash (items whose parent is not also trashed).
     *
     * @return \Illuminate\Support\Collection<int, File>
     */
    public function roots(int $ownerId): \Illuminate\Support\Collection
    {
        $trashed = File::onlyTrashed()->where('owner_id', $ownerId)->get();
        $trashedIds = $trashed->pluck('id')->all();

        return $trashed->filter(
            fn (File $f) => $f->parent_id === null || ! in_array($f->parent_id, $trashedIds, true)
        )->values();
    }

    /**
     * All nodes in a subtree (including the root), trashed or not.
     *
     * @return \Illuminate\Support\Collection<int, File>
     */
    protected function subtree(File $file): \Illuminate\Support\Collection
    {
        $nodes = collect([$file]);
        foreach ($file->children()->withTrashed()->get() as $child) {
            $nodes = $nodes->merge($this->subtree($child));
        }

        return $nodes;
    }

    protected function parentIsTrashed(File $file): bool
    {
        return $file->parent_id !== null
            && File::onlyTrashed()->whereKey($file->parent_id)->exists();
    }

    protected function forgetBlobs(File $file): void
    {
        if ($file->is_dir) {
            return;
        }

        $disk = Storage::disk($file->disk);
        $disk->delete($file->path);

        if ($file->thumbnail_path) {
            $disk->delete($file->thumbnail_path);
        }

        foreach ($file->versions()->get() as $version) {
            Storage::disk($version->disk)->delete($version->path);
        }
    }
}
