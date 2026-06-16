<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Models\Permission;
use Illuminate\Support\Collection;
use Inertia\Inertia;

class SharedController extends Controller
{
    // List items explicitly shared with the current user (directly or via group).
    public function index()
    {
        $user = auth()->user();

        $items = File::whereIn('id', $this->grantedFileIds($user))->with('owner')->get();
        $abilities = $this->abilitiesFor($items->pluck('id'), $user);

        return Inertia::render('Shared/Index', [
            'folders' => $items->where('is_dir', true)->values()
                ->map(fn (File $f) => $this->shape($f, $user, $abilities)),
            'files' => $items->where('is_dir', false)->values()
                ->map(fn (File $f) => $this->shape($f, $user, $abilities)),
        ]);
    }

    // Browse inside a shared folder (any owner) — gated by the inheriting policy.
    public function show(File $folder)
    {
        $this->authorize('view', $folder);
        abort_unless($folder->is_dir, 404);

        $user = auth()->user();
        $children = $folder->children()->with('owner')->orderByDesc('is_dir')->orderBy('name')->get();
        $abilities = $this->abilitiesFor($children->pluck('id'), $user);

        return Inertia::render('Shared/Folder', [
            'current' => ['id' => $folder->id, 'name' => $folder->name],
            'trail' => $this->sharedTrail($folder, $user),
            'folders' => $children->where('is_dir', true)->values()
                ->map(fn (File $f) => $this->shape($f, $user, $abilities)),
            'files' => $children->where('is_dir', false)->values()
                ->map(fn (File $f) => $this->shape($f, $user, $abilities)),
        ]);
    }

    // File ids the user has any direct/group grant on (the share roots).
    protected function grantedFileIds($user): Collection
    {
        return Permission::query()
            ->where(fn ($q) => $this->subjectMatch($q, $user))
            ->pluck('file_id')
            ->unique()
            ->values();
    }

    // Constrain a permissions query to grants aimed at this user or their group.
    protected function subjectMatch($query, $user): void
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
    protected function abilitiesFor(Collection $fileIds, $user): Collection
    {
        return Permission::whereIn('file_id', $fileIds)
            ->where(fn ($q) => $this->subjectMatch($q, $user))
            ->get(['file_id', 'ability'])
            ->groupBy('file_id')
            ->map(fn ($rows) => $rows->pluck('ability')->unique()->values()->all());
    }

    // Frontend shape for a shared item, including owner + the viewer's abilities.
    protected function shape(File $file, $user, Collection $abilities): array
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

    // Breadcrumb of ancestors the viewer can still see, each linking into /shared.
    protected function sharedTrail(File $folder, $user): array
    {
        $trail = [];
        for ($node = $folder->parent; $node; $node = $node->parent) {
            if (! $user->can('view', $node)) {
                break;
            }
            array_unshift($trail, ['id' => $node->id, 'name' => $node->name]);
        }

        return $trail;
    }
}
