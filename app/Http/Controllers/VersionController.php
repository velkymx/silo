<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessUploadedFile;
use App\Models\File;
use App\Models\FileVersion;
use App\Services\QuotaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class VersionController extends Controller
{
    public function download(File $file, FileVersion $version)
    {
        $this->authorize('view', $file);

        abort_unless($version->file_id === $file->id, 404);
        abort_unless(Storage::disk($version->disk)->exists($version->path), 404);

        return Storage::disk($version->disk)->download($version->path, $version->name);
    }

    public function restore(File $file, FileVersion $version): \Illuminate\Http\RedirectResponse
    {
        $this->authorize('update', $file);

        abort_unless($version->file_id === $file->id, 404);
        abort_unless(Storage::disk($version->disk)->exists($version->path), 404);

        // Restoring duplicates the version blob onto the owner's quota.
        if (app(QuotaService::class)->wouldExceed($file->owner_id, (int) $version->size - (int) $file->size)) {
            throw ValidationException::withMessages(['version' => 'Restoring this version would exceed the storage quota.']);
        }

        // Copy the version's bytes to a fresh path rather than pointing the live
        // file at the version's own blob — aliasing would let a future version
        // prune delete the live file's bytes, and double-counts storage.
        $newPath = "uploads/{$file->owner_id}/".Str::random(40);
        if ($ext = pathinfo($file->name, PATHINFO_EXTENSION)) {
            $newPath .= ".{$ext}";
        }
        Storage::disk($version->disk)->copy($version->path, $newPath);

        DB::transaction(function () use ($file, $version, $newPath) {
            app(\App\Services\FileVersioning::class)->snapshot($file);

            $file->update([
                'path' => $newPath,
                'disk' => $version->disk,
                'mime' => $version->mime,
                'size' => $version->size,
                'hash' => $version->hash,
                'version' => $file->version + 1,
                'status' => File::STATUS_PENDING,
                'content_edited_at' => now(),
            ]);
        });
        app(QuotaService::class)->invalidate($file->owner_id);

        ProcessUploadedFile::dispatch($file->id);

        return back()->with('success', "Restored version {$version->version}.");
    }
}
