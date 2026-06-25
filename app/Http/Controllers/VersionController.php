<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessUploadedFile;
use App\Models\File;
use App\Models\FileVersion;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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

        DB::transaction(function () use ($file, $version) {
            app(\App\Services\FileVersioning::class)->snapshot($file);

            $file->update([
                'path' => $version->path,
                'disk' => $version->disk,
                'mime' => $version->mime,
                'size' => $version->size,
                'hash' => $version->hash,
                'version' => $file->version + 1,
                'status' => File::STATUS_PENDING,
                'content_edited_at' => now(),
            ]);
        });

        ProcessUploadedFile::dispatch($file->id);

        return back()->with('success', "Restored version {$version->version}.");
    }
}
