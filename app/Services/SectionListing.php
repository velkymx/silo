<?php

namespace App\Services;

use App\Models\File;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Builds the item listing for a section of the unified folder shell that is
 * not a plain owned-folder view: "Shared with me" (permission grants) and
 * "Trash" (soft-deleted roots). The owner's own all/recent/starred sections
 * stay in FileController; this service owns the security-sensitive queries so
 * they live in exactly one place and are shared by SharedController.
 */
class SectionListing
{
    public function __construct(private TrashService $trash)
    {
    }

    /**
     * Files + folders explicitly shared with the user (direct or via group),
     * each carrying the owner name and the viewer's abilities.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function shared(User $user): Collection
    {
        $items = File::whereIn('id', $this->grantedFileIds($user))->with('owner')->get();
        $abilities = $this->abilitiesFor($items->pluck('id'), $user);

        return $items->map(fn (File $file) => $this->shape($file, $abilities))->values();
    }

    /**
     * The user's trashed deletion-roots (a trashed child of a trashed folder
     * is not a root). Scoped to the owner by TrashService.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function trashed(int $userId): Collection
    {
        return $this->trash->roots($userId)
            ->map(fn (File $file) => $this->trashShape($file))
            ->values();
    }

    // File ids the user has any direct/group grant on (the share roots).
    protected function grantedFileIds(User $user): Collection
    {
        return Permission::query()
            ->where(fn ($q) => $this->subjectMatch($q, $user))
            ->pluck('file_id')
            ->unique()
            ->values();
    }

    // Constrain a permissions query to grants aimed at this user or their group.
    protected function subjectMatch($query, User $user): void
    {
        $query
            ->where(fn ($x) => $x->where('subject_type', Permission::SUBJECT_USER)->where('subject_id', $user->id))
            ->when($user->group_id, fn ($x) => $x->orWhere(fn ($y) => $y
                ->where('subject_type', Permission::SUBJECT_GROUP)->where('subject_id', $user->group_id)));
    }

    /**
     * Abilities the viewer holds on each of the given files, fetched in one
     * query (avoids an N+1 of one permission lookup per shaped row).
     *
     * @return Collection<int, array<int, string>>
     */
    public function abilitiesFor(Collection $fileIds, User $user): Collection
    {
        return Permission::whereIn('file_id', $fileIds)
            ->where(fn ($q) => $this->subjectMatch($q, $user))
            ->get(['file_id', 'ability'])
            ->groupBy('file_id')
            ->map(fn ($rows) => $rows->pluck('ability')->unique()->values()->all());
    }

    /**
     * Frontend shape for a shared item, including owner + the viewer's abilities.
     *
     * @return array<string, mixed>
     */
    public function shape(File $file, Collection $abilities): array
    {
        return [
            'id' => $file->id,
            'name' => $file->name,
            'is_dir' => $file->is_dir,
            'size' => $file->size,
            'type' => strtolower(pathinfo($file->name, PATHINFO_EXTENSION)),
            'mime' => $file->mime,
            'url' => $file->is_dir ? null : route('files.raw', $file),
            'thumb_url' => $file->thumbnail_path ? route('files.thumbnail', $file) : null,
            'owner' => $file->owner?->name,
            'abilities' => $abilities->get($file->id, []),
            'created_at' => $file->created_at->format('Y-m-d H:i'),
        ];
    }

    /**
     * Frontend shape for a trashed item.
     *
     * @return array<string, mixed>
     */
    protected function trashShape(File $file): array
    {
        return [
            'id' => $file->id,
            'name' => $file->name,
            'is_dir' => $file->is_dir,
            'size' => $file->size,
            'type' => strtolower(pathinfo($file->name, PATHINFO_EXTENSION)),
            'deleted_at' => $file->deleted_at?->format('Y-m-d H:i'),
        ];
    }
}
