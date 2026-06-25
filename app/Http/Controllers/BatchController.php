<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ManagesFilesystem;
use App\Models\File;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BatchController extends Controller
{
    use ManagesFilesystem;

    public function move(Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
            'target_id' => ['nullable', 'integer', 'exists:files,id'],
        ]);

        $userId = auth()->id();
        $target = $this->resolveFolder($data['target_id'] ?? null, $userId);

        $this->withFolderLock($userId, $target?->id, function () use ($data, $target) {
            DB::transaction(function () use ($data, $target) {
                foreach ($this->ownedBatch($data['ids']) as $file) {
                    if ($target && $file->is_dir && $this->isSelfOrDescendant($file, $target)) {
                        throw ValidationException::withMessages(['target_id' => "Cannot move \"{$file->name}\" into itself."]);
                    }
                    $this->assertNoCollision($target?->id, $file->name, $file->owner_id, $file->id);
                    $file->update(['parent_id' => $target?->id]);
                }
            });
        });

        return redirect()->route('files.index', ['folder' => $target?->id])
            ->with('success', 'Moved selected items.');
    }

    public function delete(Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($this->ownedBatch($data['ids']) as $file) {
                if ($file->is_dir) {
                    $this->trashSubtree($file);
                }
                $file->delete();
                Audit::log('file.trash', $file);
            }
        });

        return back()->with('success', 'Moved selected items to trash.');
    }

    public function folder(Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'ids' => ['required', 'array'],
            'ids.*' => ['integer'],
            'parent_id' => ['nullable', 'integer', 'exists:files,id'],
        ]);

        $userId = auth()->id();
        $parent = $this->resolveFolder($data['parent_id'] ?? null, $userId);
        $name = trim($data['name']);

        $folder = $this->withFolderLock($userId, $parent?->id, function () use ($name, $parent, $userId, $data) {
            $this->assertNoCollision($parent?->id, $name, $userId);

            return DB::transaction(function () use ($name, $parent, $userId, $data) {
                $folder = File::create([
                    'name' => $name,
                    'path' => $name,
                    'disk' => config('filemanager.disk'),
                    'is_dir' => true,
                    'parent_id' => $parent?->id,
                    'owner_id' => $userId,
                ]);

                foreach ($this->ownedBatch($data['ids']) as $file) {
                    if ($file->id === $folder->id) {
                        continue;
                    }
                    $this->assertNoCollision($folder->id, $file->name, $userId, $file->id);
                    $file->update(['parent_id' => $folder->id]);
                }

                return $folder;
            });
        });

        return redirect()->route('files.index', ['folder' => $parent?->id])
            ->with('success', "Created \"{$folder->name}\" from selection.");
    }

    public function rename(Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate([
            'renames' => ['required', 'array'],
            'renames.*.id' => ['required', 'integer'],
            'renames.*.name' => ['required', 'string', 'max:255'],
        ]);

        $userId = auth()->id();
        $byId = collect($data['renames'])->keyBy('id');

        DB::transaction(function () use ($byId, $userId) {
            $files = File::whereIn('id', $byId->keys())->where('owner_id', $userId)->get();
            foreach ($files as $file) {
                $name = trim((string) $byId[$file->id]['name']);
                if ($name === '' || $name === $file->name) {
                    continue;
                }
                $this->assertNoCollision($file->parent_id, $name, $userId, $file->id);
                $file->update(['name' => $name]);
            }
        });

        return back()->with('success', 'Renamed selected items.');
    }

    private function ownedBatch(array $ids): \Illuminate\Support\Collection
    {
        $userId = auth()->id();
        $files = File::whereIn('id', $ids)->where('owner_id', $userId)->get()->keyBy('id');

        return collect($ids)->map(fn ($id) => $files->get($id))->filter()->values();
    }

}
