<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesFilesystem;
use App\Models\File;
use Illuminate\Http\Request;

class FolderController extends Controller
{
    use ManagesFilesystem;

    /**
     * ME-03: lazy folder lookup for the move/copy picker. Returns the current
     * user's folders, optionally filtered by parent and/or a case-insensitive
     * name search. Capped at 200 rows so the payload stays bounded.
     */
    public function index(Request $request)
    {
        $userId = $request->user()->id;
        $query = File::folders()->where('owner_id', $userId);
        if ($request->filled('parent')) {
            $query->where('parent_id', $request->integer('parent'));
        }
        if ($search = trim((string) $request->input('q', ''))) {
            // LIKE wildcard escape so user input is treated as a literal substring.
            $escaped = addcslashes($search, '%_\\');
            $query->where('name', 'like', '%'.$escaped.'%');
        }
        return response()->json(
            $query->orderBy('name')->limit(200)->get(['id', 'name', 'parent_id'])
        );
    }

    public function store(Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'folder_name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer', 'exists:files,id'],
        ]);

        $userId = auth()->id();
        $parent = $this->resolveFolder($data['parent_id'] ?? null, $userId);
        $name = trim($data['folder_name']);

        $this->withFolderLock($userId, $parent?->id, function () use ($name, $parent, $userId) {
            $this->assertNoCollision($parent?->id, $name, $userId);

            File::create([
                'name' => $name,
                'path' => $name,
                'disk' => config('filemanager.disk'),
                'is_dir' => true,
                'parent_id' => $parent?->id,
                'owner_id' => $userId,
            ]);
        });

        return redirect()->route('files.index', ['folder' => $parent?->id])
            ->with('success', "Folder '{$name}' created successfully!");
    }

    public function show(File $folder): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('view', $folder);

        return redirect()->route('files.index', ['folder' => $folder->id]);
    }
}

