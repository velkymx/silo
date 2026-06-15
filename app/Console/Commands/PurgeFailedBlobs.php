<?php

namespace App\Console\Commands;

use App\Models\File;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('failed_blobs:purge {--days=7 : Age in days a failed file must exceed}')]
#[Description('Delete failed-status files (and any lingering blob) older than N days.')]
class PurgeFailedBlobs extends Command
{
    public function handle(): int
    {
        $cutoff = now()->subDays((int) $this->option('days'));

        $purged = 0;
        File::withTrashed()
            ->where('status', File::STATUS_FAILED)
            ->where('updated_at', '<', $cutoff)
            ->chunkById(200, function ($files) use (&$purged) {
                foreach ($files as $file) {
                    if (! $file->referenced && $file->path) {
                        Storage::disk($file->disk)->delete($file->path);
                    }
                    $file->forceDelete();
                    $purged++;
                }
            });

        $this->info("Purged {$purged} failed file(s).");

        return self::SUCCESS;
    }
}
