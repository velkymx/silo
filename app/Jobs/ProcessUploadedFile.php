<?php

namespace App\Jobs;

use App\Models\File;
use App\Services\Audit;
use App\Services\MetadataExtractor;
use App\Services\ThumbnailGenerator;
use App\Services\VirusScanner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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
    public function handle(MetadataExtractor $extractor, ThumbnailGenerator $thumbnails, VirusScanner $scanner): void
    {
        // Serialize against a duplicate dispatch for the same file so two
        // workers can't interleave scan/metadata/thumbnail writes.
        $lock = Cache::lock("process-file-{$this->fileId}", 300);
        if (! $lock->get()) {
            return;
        }

        try {
            $this->process($extractor, $thumbnails, $scanner);
        } finally {
            $lock->release();
        }
    }

    private function process(MetadataExtractor $extractor, ThumbnailGenerator $thumbnails, VirusScanner $scanner): void
    {
        $file = File::find($this->fileId);

        if (! $file || $file->is_dir) {
            return;
        }

        // CAS guard: only the first job that finds the file still PENDING does
        // the work; a duplicate that runs after completion sees a terminal
        // status and bails (idempotent, exactly one thumbnail).
        if ($file->status !== File::STATUS_PENDING) {
            return;
        }

        $disk = Storage::disk($file->disk);

        if (! $disk->exists($file->path)) {
            $file->update(['status' => File::STATUS_FAILED]);

            return;
        }

        // Antivirus gate (fail closed): infected blobs are deleted, scanner
        // errors leave the file failed and unservable.
        $verdict = $scanner->scan($file);
        if ($verdict === VirusScanner::INFECTED) {
            $disk->delete($file->path);
            $file->update(['status' => File::STATUS_INFECTED, 'thumbnail_path' => null]);
            Audit::log('file.infected', $file);
            Log::warning('file.infected', ['file_id' => $file->id, 'owner_id' => $file->owner_id]);

            return;
        }
        if ($verdict === VirusScanner::ERROR) {
            // Unscannable → fail closed AND drop the (uploaded) blob so it can't
            // orphan. Never delete a referenced/imported original.
            if (! $file->referenced) {
                $disk->delete($file->path);
            }
            $file->update(['status' => File::STATUS_FAILED]);
            Log::error('file.scan_error', ['file_id' => $file->id]);

            return;
        }

        // Refine the MIME type from the stored bytes (client-reported types lie).
        if ($mime = $disk->mimeType($file->path)) {
            $file->mime = $mime;
        }

        $metadata = $extractor->extract($file);
        $thumbnailPath = $thumbnails->generate($file);

        $file->update([
            'mime' => $file->mime,
            'metadata' => $metadata ?: null,
            'thumbnail_path' => $thumbnailPath,
            'status' => File::STATUS_READY,
        ]);
    }

    /**
     * Mark the file as failed if the job blows up.
     */
    public function failed(Throwable $exception): void
    {
        $file = File::find($this->fileId);
        if (! $file) {
            return;
        }

        // Drop the unservable blob (unless it's a referenced/imported original)
        // so a failed upload doesn't orphan bytes on disk.
        if (! $file->referenced && $file->path) {
            Storage::disk($file->disk)->delete($file->path);
        }
        $file->update(['status' => File::STATUS_FAILED]);
    }
}
