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

        return Inertia::render('Shared/Index', [
            'folders' => $items->where('is_dir', true)->values()
                ->map(fn (File $f) => $this->shape($f, $user)),
            'files' => $items->where('is_dir', false)->values()
                ->map(fn (File $f) => $this->shape($f, $user)),
        ]);
    }

    // Browse inside a shared folder (any owner) — gated by the inheriting policy.
    public function show(File $folder)
    {
        $this->authorize('view', $folder);
        abort_unless($folder->is_dir, 404);

        $user = auth()->user();
        $children = $folder->children()->with('owner')->orderByDesc('is_dir')->orderBy('name')->get();

        return Inertia::render('Shared/Folder', [
            'current' => ['id' => $folder->id, 'name' => $folder->name],
            'trail' => $this->sharedTrail($folder, $user),
            'folders' => $children->where('is_dir', true)->values()
                ->map(fn (File $f) => $this->shape($f, $user)),
            'files' => $children->where('is_dir', false)->values()
                ->map(fn (File $f) => $this->shape($f, $user)),
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

    // Frontend shape for a shared item, including owner + the viewer's abilities.
    protected function shape(File $file, $user): array
    {
        $abilities = Permission::where('file_id', $file->id)
            ->where(fn ($q) => $this->subjectMatch($q, $user))
            ->pluck('ability')->unique()->values();

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
            'abilities' => $abilities,
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
