<?php

namespace App\Http\Controllers;

use App\Models\File;
use App\Services\Audit;
use App\Services\TrashService;

class TrashController extends Controller
{
    public function __construct(private TrashService $trash)
    {
    }

    // Restore a trashed item (and its subtree).
    public function restore(File $file)
    {
        $this->authorize('restore', $file);
        $this->trash->restore($file);
        Audit::log('file.restore', $file);

        return back()->with('success', 'Restored.');
    }

    // Restore multiple trashed items (undo for batch-delete).
    public function batchRestore(\Illuminate\Http\Request $request)
    {
        $request->validate(['ids' => 'required|array', 'ids.*' => 'integer']);
        $files = File::withTrashed()
            ->whereIn('id', $request->ids)
            ->where('owner_id', auth()->id())
            ->get();

        foreach ($files as $file) {
            $this->authorize('restore', $file);
            $this->trash->restore($file);
            Audit::log('file.restore', $file);
        }

        return back()->with('success', count($files) . ' item(s) restored.');
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
        $roots = $this->trash->roots(auth()->id());
        foreach ($roots as $root) {
            $this->authorize('forceDelete', $root);
        }

        // Single outer transaction so the whole empty is atomic and opens one
        // transaction, not one per root.
        \Illuminate\Support\Facades\DB::transaction(function () use ($roots) {
            foreach ($roots as $root) {
                $this->trash->purge($root);
            }
        });

        return back()->with('success', 'Trash emptied.');
    }
}
