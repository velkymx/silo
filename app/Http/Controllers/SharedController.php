<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Services\SectionListing;
use Inertia\Inertia;

class SharedController extends Controller
{
    // List items explicitly shared with the current user (directly or via group).
    public function index(SectionListing $listing)
    {
        $items = $listing->shared(auth()->user());

        return Inertia::render('Shared/Index', [
            'folders' => $items->where('is_dir', true)->values(),
            'files' => $items->where('is_dir', false)->values(),
        ]);
    }

    // Browse inside a shared folder (any owner) — gated by the inheriting policy.
    public function show(File $folder, SectionListing $listing)
    {
        $this->authorize('view', $folder);
        abort_unless($folder->is_dir, 404);

        $user = auth()->user();
        $children = $folder->children()->with('owner')->orderByDesc('is_dir')->orderBy('name')->get();
        $abilities = $listing->abilitiesFor($children->pluck('id'), $user);

        return Inertia::render('Shared/Folder', [
            'current' => ['id' => $folder->id, 'name' => $folder->name],
            'trail' => $this->sharedTrail($folder, $user),
            'folders' => $children->where('is_dir', true)->values()
                ->map(fn (File $f) => $listing->shape($f, $abilities)),
            'files' => $children->where('is_dir', false)->values()
                ->map(fn (File $f) => $listing->shape($f, $abilities)),
        ]);
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
