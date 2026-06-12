<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessUploadedFile;
use App\Models\File;
use App\Models\FileVersion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

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

        $folders = (clone $query)->folders()->withCount('children')->orderBy($sort, $direction)->get()
            ->map(fn (File $folder) => [
                'id' => $folder->id,
                'name' => $folder->name,
                'item_count' => $folder->children_count,
                'updated_at' => $folder->updated_at->format('Y-m-d H:i'),
            ]);

        $files = (clone $query)->files()->with('versions')->orderBy($sort, $direction)->get()
            ->map(fn (File $file) => $this->transform($file));

        return Inertia::render('Files/Index', [
            'folders' => $folders,
            'files' => $files,
            'current' => $current ? ['id' => $current->id, 'name' => $current->name] : null,
            'breadcrumbs' => $this->breadcrumbs($current),
            // Flat list of every folder the user owns — used by the move/copy destination picker.
            'allFolders' => File::folders()->where('owner_id', $userId)
                ->orderBy('name')->get(['id', 'name', 'parent_id']),
            'filters' => [
                'search' => $request->string('search')->toString(),
                'sort' => $sort,
                'direction' => $direction,
            ],
        ]);
    }

    // Shape a file model for the frontend.
    protected function transform(File $file): array
    {
        return [
            'id' => $file->id,
            'name' => $file->name,
            'size' => $file->size,
            'mime' => $file->mime,
            'type' => strtolower(pathinfo($file->name, PATHINFO_EXTENSION)),
            'url' => Storage::disk($file->disk)->url($file->path),
            'status' => $file->status,
            'metadata' => $file->metadata,
            'hash' => $file->hash,
            'version' => $file->version,
            'versions' => $file->relationLoaded('versions')
                ? $file->versions->map(fn (FileVersion $v) => [
                    'id' => $v->id,
                    'version' => $v->version,
                    'size' => $v->size,
                    'created_at' => $v->created_at->format('Y-m-d H:i'),
                ])->values()
                : [],
            'created_at' => $file->created_at->format('Y-m-d H:i'),
        ];
    }

    // Build the breadcrumb trail from root to the current folder.
    protected function breadcrumbs(?File $current): array
    {
        $trail = [];
        for ($node = $current; $node; $node = $node->parent) {
            array_unshift($trail, ['id' => $node->id, 'name' => $node->name]);
        }

        return $trail;
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
        $disk = config('filemanager.disk');

        foreach ($request->file('files', []) as $upload) {
            // Storage is flat per user; the folder hierarchy lives entirely in the DB.
            $path = $upload->store("uploads/{$userId}", $disk);

            $attributes = [
                'name' => $upload->getClientOriginalName(),
                'path' => $path,
                'disk' => $disk,
                'mime' => $upload->getClientMimeType(),
                'size' => $upload->getSize(),
                'hash' => hash_file('sha256', $upload->getRealPath()),
                'status' => File::STATUS_PENDING,
            ];

            // An upload onto an existing file name becomes a new version of that file.
            $existing = File::files()
                ->where('owner_id', $userId)
                ->where('parent_id', $parent?->id)
                ->where('name', $upload->getClientOriginalName())
                ->first();

            $file = $existing
                ? $this->overwrite($existing, $attributes, $userId)
                : File::create($attributes + ['is_dir' => false, 'parent_id' => $parent?->id, 'owner_id' => $userId]);

            // Refine mime + extract metadata off the request cycle.
            ProcessUploadedFile::dispatch($file->id);
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

        DB::transaction(function () use ($file) {
            if ($file->is_dir) {
                $this->deleteSubtree($file);
            } else {
                Storage::disk($file->disk)->delete($file->path);
            }

            $file->delete();
        });

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
        $name = trim((string) $request->string('folder_name'));

        $this->assertNoCollision($parent?->id, $name, $userId);

        File::create([
            'name' => $name,
            'path' => $name, // informational only; folders have no disk directory
            'disk' => config('filemanager.disk'),
            'is_dir' => true,
            'parent_id' => $parent?->id,
            'owner_id' => $userId,
        ]);

        return redirect()->route('files.index', ['folder' => $parent?->id])
            ->with('success', "Folder '{$name}' created successfully!");
    }

    // Rename a file or folder (display name only; storage path is stable).
    public function rename(Request $request, File $file)
    {
        $this->authorize('update', $file);

        $request->validate(['name' => 'required|string|max:255']);
        $name = trim((string) $request->string('name'));

        $this->assertNoCollision($file->parent_id, $name, $file->owner_id, $file->id);

        $file->update(['name' => $name]);

        return back()->with('success', 'Renamed successfully!');
    }

    // Move a file or folder into another folder (DB reparent only).
    public function move(Request $request, File $file)
    {
        $this->authorize('update', $file);

        $request->validate(['target_id' => 'nullable|integer|exists:files,id']);
        $target = $this->resolveFolder($request->input('target_id'), $file->owner_id);

        if ($target && $file->is_dir && $this->isSelfOrDescendant($file, $target)) {
            throw ValidationException::withMessages([
                'target_id' => 'Cannot move a folder into itself or one of its subfolders.',
            ]);
        }

        $this->assertNoCollision($target?->id, $file->name, $file->owner_id, $file->id);

        $file->update(['parent_id' => $target?->id]);

        return redirect()->route('files.index', ['folder' => $target?->id])
            ->with('success', 'Moved successfully!');
    }

    // Copy a file or folder (deep) into another folder.
    public function copy(Request $request, File $file)
    {
        $this->authorize('view', $file);

        $request->validate(['target_id' => 'nullable|integer|exists:files,id']);
        $target = $this->resolveFolder($request->input('target_id'), $file->owner_id);

        if ($target && $file->is_dir && $this->isSelfOrDescendant($file, $target)) {
            throw ValidationException::withMessages([
                'target_id' => 'Cannot copy a folder into itself or one of its subfolders.',
            ]);
        }

        $name = $this->uniqueName($target?->id, $file->name, $file->owner_id);

        DB::transaction(fn () => $this->copyNode($file, $target?->id, $name));

        return redirect()->route('files.index', ['folder' => $target?->id])
            ->with('success', 'Copied successfully!');
    }

    // Download a specific historical version of a file.
    public function downloadVersion(File $file, FileVersion $version)
    {
        $this->authorize('view', $file);

        abort_unless($version->file_id === $file->id, 404);
        abort_unless(Storage::disk($version->disk)->exists($version->path), 404);

        return Storage::disk($version->disk)->download($version->path, $version->name);
    }

    // Restore a historical version as the file's current content.
    public function restoreVersion(File $file, FileVersion $version)
    {
        $this->authorize('update', $file);

        abort_unless($version->file_id === $file->id, 404);

        DB::transaction(function () use ($file, $version) {
            // Preserve the current content as a version before overwriting it.
            $this->snapshotVersion($file);

            $file->update([
                'path' => $version->path,
                'disk' => $version->disk,
                'mime' => $version->mime,
                'size' => $version->size,
                'hash' => $version->hash,
                'version' => $file->version + 1,
                'status' => File::STATUS_PENDING,
            ]);
        });

        ProcessUploadedFile::dispatch($file->id);

        return back()->with('success', "Restored version {$version->version}.");
    }

    // Navigate into a folder resolved by DB id.
    public function viewFolder(File $folder)
    {
        $this->authorize('view', $folder);

        return redirect()->route('files.index', ['folder' => $folder->id]);
    }

    // Replace a file's content, archiving its current blob as a version.
    protected function overwrite(File $file, array $attributes, int $userId): File
    {
        DB::transaction(function () use ($file, $attributes, $userId) {
            $this->snapshotVersion($file, $userId);

            $file->update($attributes + ['version' => $file->version + 1]);
        });

        return $file;
    }

    // Archive the file's current blob as a historical version row.
    protected function snapshotVersion(File $file, ?int $createdBy = null): void
    {
        FileVersion::create([
            'file_id' => $file->id,
            'version' => $file->version,
            'name' => $file->name,
            'path' => $file->path,
            'disk' => $file->disk,
            'mime' => $file->mime,
            'size' => $file->size,
            'hash' => $file->hash,
            'created_by' => $createdBy ?? $file->owner_id,
        ]);
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

    // Recursively delete a folder's descendants (DB rows + file blobs).
    protected function deleteSubtree(File $folder): void
    {
        foreach ($folder->children as $child) {
            if ($child->is_dir) {
                $this->deleteSubtree($child);
            } else {
                Storage::disk($child->disk)->delete($child->path);
            }

            $child->delete();
        }
    }

    // Recursively copy a node under a new parent, duplicating file blobs.
    protected function copyNode(File $source, ?int $parentId, string $name): File
    {
        $disk = config('filemanager.disk');
        $path = $source->name; // folder placeholder

        if (! $source->is_dir) {
            $path = "uploads/{$source->owner_id}/".Str::random(40);
            if ($extension = pathinfo($source->path, PATHINFO_EXTENSION)) {
                $path .= ".{$extension}";
            }
            Storage::disk($source->disk)->copy($source->path, $path);
        }

        $copy = File::create([
            'name' => $name,
            'path' => $path,
            'disk' => $source->is_dir ? $disk : $source->disk,
            'is_dir' => $source->is_dir,
            'mime' => $source->mime,
            'size' => $source->size,
            'hash' => $source->hash,
            'status' => $source->status,
            'metadata' => $source->metadata,
            'parent_id' => $parentId,
            'owner_id' => $source->owner_id,
        ]);

        if ($source->is_dir) {
            foreach ($source->children as $child) {
                $this->copyNode($child, $copy->id, $child->name);
            }
        }

        return $copy;
    }

    // True when $target is $folder itself or sits inside its subtree.
    protected function isSelfOrDescendant(File $folder, File $target): bool
    {
        for ($node = $target; $node; $node = $node->parent) {
            if ($node->id === $folder->id) {
                return true;
            }
        }

        return false;
    }

    // Reject a name that already exists among siblings.
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

    // Produce a non-colliding name by appending "(copy)" / "(copy N)".
    protected function uniqueName(?int $parentId, string $name, int $ownerId): string
    {
        $base = $name;
        $extension = '';
        if (($dot = strrpos($name, '.')) !== false && $dot > 0) {
            $base = substr($name, 0, $dot);
            $extension = substr($name, $dot);
        }

        $candidate = $name;
        $n = 0;
        while (File::where('owner_id', $ownerId)->where('parent_id', $parentId)->where('name', $candidate)->exists()) {
            $n++;
            $suffix = $n === 1 ? ' (copy)' : " (copy {$n})";
            $candidate = "{$base}{$suffix}{$extension}";
        }

        return $candidate;
    }
}
