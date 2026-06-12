<?php

namespace App\Http\Controllers;

use App\Models\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    // Display files and folders for the current (DB-backed) folder.
    public function index(Request $request)
    {
        $userId = auth()->id();

        $current = null;
        if ($request->filled('folder')) {
            $current = File::folders()->where('owner_id', $userId)->findOrFail($request->integer('folder'));
            $this->authorize('view', $current);
        }

        $query = File::query()
            ->where('owner_id', $userId)
            ->where('parent_id', $current?->id);

        if ($request->filled('search')) {
            $query->where('name', 'like', '%'.$request->string('search').'%');
        }

        $sort = in_array($request->get('sort'), ['name', 'size', 'created_at'], true)
            ? $request->get('sort')
            : 'name';
        $direction = $request->get('direction') === 'desc' ? 'desc' : 'asc';

        $folders = (clone $query)->folders()->orderBy($sort, $direction)->get();
        $files = (clone $query)->files()->orderBy($sort, $direction)->get();

        return view('files.index', compact('folders', 'files', 'current', 'sort', 'direction'));
    }

    // Upload one or more files into the current folder.
    public function upload(Request $request)
    {
        $request->validate([
            'files.*' => 'required|file|max:'.config('filemanager.max_upload_kb'),
            'parent_id' => 'nullable|integer|exists:files,id',
        ]);

        $userId = auth()->id();
        $parent = $this->resolveFolder($request->input('parent_id'), $userId);
        $dir = $parent?->path ?? "uploads/{$userId}";
        $disk = config('filemanager.disk');

        foreach ($request->file('files', []) as $upload) {
            $path = $upload->store($dir, $disk);

            File::create([
                'name' => $upload->getClientOriginalName(),
                'path' => $path,
                'disk' => $disk,
                'is_dir' => false,
                'mime' => $upload->getClientMimeType(),
                'size' => $upload->getSize(),
                'hash' => hash_file('sha256', $upload->getRealPath()),
                'parent_id' => $parent?->id,
                'owner_id' => $userId,
            ]);
        }

        return redirect()->route('files.index', ['folder' => $parent?->id])
            ->with('success', 'Files uploaded successfully!');
    }

    // Download a file resolved by DB id (no client-supplied paths).
    public function download(File $file)
    {
        $this->authorize('view', $file);

        abort_if($file->is_dir, 404);
        abort_unless(Storage::disk($file->disk)->exists($file->path), 404);

        return Storage::disk($file->disk)->download($file->path, $file->name);
    }

    // Delete a file or folder (and its contents) resolved by DB id.
    public function destroy(File $file)
    {
        $this->authorize('delete', $file);

        $disk = Storage::disk($file->disk);

        if ($file->is_dir) {
            $disk->deleteDirectory($file->path);
            $this->deleteSubtree($file);
        } else {
            $disk->delete($file->path);
        }

        $file->delete();

        return redirect()->route('files.index', ['folder' => $file->parent_id])
            ->with('success', 'Deleted successfully!');
    }

    // Create a new folder inside the current folder.
    public function createFolder(Request $request)
    {
        $request->validate([
            'folder_name' => 'required|string|max:255',
            'parent_id' => 'nullable|integer|exists:files,id',
        ]);

        $userId = auth()->id();
        $parent = $this->resolveFolder($request->input('parent_id'), $userId);
        $name = (string) $request->string('folder_name');
        $disk = config('filemanager.disk');

        $path = ($parent?->path ?? "uploads/{$userId}")."/{$name}";
        Storage::disk($disk)->makeDirectory($path);

        File::create([
            'name' => $name,
            'path' => $path,
            'disk' => $disk,
            'is_dir' => true,
            'parent_id' => $parent?->id,
            'owner_id' => $userId,
        ]);

        return redirect()->route('files.index', ['folder' => $parent?->id])
            ->with('success', "Folder '{$name}' created successfully!");
    }

    // Navigate into a folder resolved by DB id.
    public function viewFolder(File $folder)
    {
        $this->authorize('view', $folder);

        return redirect()->route('files.index', ['folder' => $folder->id]);
    }

    // Resolve a folder owned by the user, or null for the root.
    protected function resolveFolder($id, int $userId): ?File
    {
        if (! $id) {
            return null;
        }

        $folder = File::folders()->where('owner_id', $userId)->findOrFail($id);
        $this->authorize('update', $folder);

        return $folder;
    }

    // Recursively soft-delete a folder's descendants.
    protected function deleteSubtree(File $folder): void
    {
        foreach ($folder->children as $child) {
            if ($child->is_dir) {
                $this->deleteSubtree($child);
            }

            $child->delete();
        }
    }
}
