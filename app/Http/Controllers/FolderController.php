<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesFilesystem;
use App\Models\File;
use Illuminate\Http\Request;

class FolderController extends Controller
{
    use ManagesFilesystem;

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
