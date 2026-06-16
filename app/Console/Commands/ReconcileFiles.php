<?php

namespace App\Console\Commands;

use App\Jobs\ProcessUploadedFile;
use App\Models\File;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('files:reconcile {--minutes=15 : Re-dispatch processing for files stuck pending longer than this}')]
#[Description('Re-queue files whose post-upload processing never completed (e.g. the queue worker died)')]
class ReconcileFiles extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $cutoff = now()->subMinutes((int) $this->option('minutes'));

        $stuck = File::query()
            ->where('is_dir', false)
            ->where('status', File::STATUS_PENDING)
            ->where('updated_at', '<', $cutoff)
            ->pluck('id');

        foreach ($stuck as $id) {
            ProcessUploadedFile::dispatch($id);
        }

        $this->info("Re-dispatched {$stuck->count()} stuck file(s).");

        return self::SUCCESS;
    }
}
