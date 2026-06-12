<?php

namespace App\Jobs;

use App\Models\File;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProcessUploadedFile implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public int $fileId)
    {
    }

    /**
     * Execute the job: refine metadata for an uploaded file.
     */
    public function handle(): void
    {
        $file = File::find($this->fileId);

        if (! $file || $file->is_dir) {
            return;
        }

        $disk = Storage::disk($file->disk);

        if (! $disk->exists($file->path)) {
            $file->update(['status' => File::STATUS_FAILED]);

            return;
        }

        $metadata = [];

        // Refine the MIME type from the stored bytes (client-reported types lie).
        if ($mime = $disk->mimeType($file->path)) {
            $file->mime = $mime;
        }

        // Extract image dimensions when the file is an image.
        if (str_starts_with((string) $file->mime, 'image/')) {
            $info = @getimagesizefromstring($disk->get($file->path));
            if ($info !== false) {
                $metadata['width'] = $info[0];
                $metadata['height'] = $info[1];
            }
        }

        $file->update([
            'mime' => $file->mime,
            'metadata' => $metadata ?: null,
            'status' => File::STATUS_READY,
        ]);
    }

    /**
     * Mark the file as failed if the job blows up.
     */
    public function failed(Throwable $exception): void
    {
        File::whereKey($this->fileId)->update(['status' => File::STATUS_FAILED]);
    }
}
