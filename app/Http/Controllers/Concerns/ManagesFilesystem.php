<?php

namespace App\Http\Controllers\Concerns;

use App\Models\File;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

trait ManagesFilesystem
{
    protected function resolveFolder($id, int $userId): ?File
    {
        if (! $id) {
            return null;
        }

        $folder = File::folders()->where('owner_id', $userId)->findOrFail($id);
        $this->authorize('update', $folder);

        return $folder;
    }

    protected function assertNoCollision(?int $parentId, string $name, int $ownerId, ?int $ignoreId = null): void
    {
        $exists = File::where('owner_id', $ownerId)
            ->where('parent_id', $parentId)
            ->where('name', $name)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => "An item named \"{$name}\" already exists here.",
            ]);
        }
    }

    protected function withFolderLock(int $ownerId, ?int $parentId, Closure $callback): mixed
    {
        $key = "file-write:{$ownerId}:".($parentId ?? 'root');

        return Cache::lock($key, 10)->block(5, $callback);
    }

    protected function trashSubtree(File $folder): void
    {
        foreach ($folder->children as $child) {
            if ($child->is_dir) {
                $this->trashSubtree($child);
            }

            $child->delete();
        }
    }

    protected function isSelfOrDescendant(File $folder, File $target): bool
    {
        for ($node = $target; $node; $node = $node->parent) {
            if ($node->id === $folder->id) {
                return true;
            }
        }

        return false;
    }
}
