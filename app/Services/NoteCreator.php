<?php

namespace App\Services;

use App\Jobs\ProcessUploadedFile;
use App\Jobs\SyncNoteLinks;
use App\Models\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Creates a markdown note File: quota check, collision-safe name, blob write,
 * model row, post-processing jobs, audit. Shared by the Notes surface and
 * anything else that mints notes (e.g. upsizing a wall post).
 */
class NoteCreator
{
    public function __construct(private readonly QuotaService $quota) {}

    /** Lazily create (and return) a user's root Notes folder. */
    public function rootFor(int $userId): File
    {
        $name = config('filemanager.notes.root_folder', 'Notes');

        return File::firstOrCreate(
            ['owner_id' => $userId, 'parent_id' => null, 'name' => $name, 'is_dir' => true],
            ['path' => $name, 'disk' => config('filemanager.disk')],
        );
    }

    /**
     * Create a note in $parent. $name should already end in .md; it is made
     * collision-safe here. Throws a validation error when over quota.
     */
    public function create(int $userId, File $parent, string $name, string $content): File
    {
        if ($this->quota->wouldExceed($userId, strlen($content))) {
            throw ValidationException::withMessages(['name' => 'This would exceed your storage quota.']);
        }

        $disk = config('filemanager.disk');
        $name = $this->uniqueName($parent->id, $name, $userId);
        $path = "uploads/{$userId}/".Str::random(40).'.md';
        Storage::disk($disk)->put($path, $content);

        $file = File::create([
            'name' => $name,
            'path' => $path,
            'disk' => $disk,
            'is_dir' => false,
            'mime' => 'text/markdown',
            'size' => strlen($content),
            'hash' => hash('sha256', $content),
            'status' => File::STATUS_PENDING,
            'content_edited_at' => now(),
            'parent_id' => $parent->id,
            'owner_id' => $userId,
        ]);

        ProcessUploadedFile::dispatch($file->id);
        SyncNoteLinks::dispatch($file->id);
        Audit::log('note.create', $file);

        return $file;
    }

    /** "name (copy).md", "name (copy 2).md", … until free within the folder. */
    public function uniqueName(int $parentId, string $name, int $ownerId): string
    {
        $base = $name;
        $ext = '';
        if (($dot = strrpos($name, '.')) !== false && $dot > 0) {
            $base = substr($name, 0, $dot);
            $ext = substr($name, $dot);
        }

        $candidate = $name;
        $n = 0;
        while (File::where('owner_id', $ownerId)->where('parent_id', $parentId)->where('name', $candidate)->exists()) {
            $n++;
            $candidate = $base.($n === 1 ? ' (copy)' : " (copy {$n})").$ext;
        }

        return $candidate;
    }
}
