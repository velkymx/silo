<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Services\Audit;
use App\Services\TrashService;
use Inertia\Inertia;

class TrashController extends Controller
{
    public function __construct(private TrashService $trash)
    {
    }

    // List the user's trashed items (deletion roots only).
    public function index()
    {
        $items = $this->trash->roots(auth()->id())->map(fn (File $f) => [
            'id' => $f->id,
            'name' => $f->name,
            'is_dir' => $f->is_dir,
            'size' => $f->size,
            'type' => strtolower(pathinfo($f->name, PATHINFO_EXTENSION)),
            'deleted_at' => $f->deleted_at?->format('Y-m-d H:i'),
        ]);

        return Inertia::render('Trash/Index', ['items' => $items]);
    }

    // Restore a trashed item (and its subtree).
    public function restore(File $file)
    {
        $this->authorize('restore', $file);
        $this->trash->restore($file);
        Audit::log('file.restore', $file);

        return back()->with('success', 'Restored.');
    }

    // Permanently delete a trashed item (and its subtree + blobs).
    public function destroy(File $file)
    {
        $this->authorize('forceDelete', $file);
        Audit::log('file.purge', $file);
        $this->trash->purge($file);

        return back()->with('success', 'Permanently deleted.');
    }

    // Empty the whole trash for the user.
    public function empty()
    {
        foreach ($this->trash->roots(auth()->id()) as $root) {
            $this->authorize('forceDelete', $root);
            $this->trash->purge($root);
        }

        return back()->with('success', 'Trash emptied.');
    }
}
