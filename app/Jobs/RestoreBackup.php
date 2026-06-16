<?php

namespace App\Jobs;

use App\Models\Backup;
use App\Services\BackupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RestoreBackup implements ShouldQueue
{
    use Queueable;

    public int $timeout = 3600;

    public function __construct(public int $backupId)
    {
    }

    public function handle(BackupService $backups): void
    {
        $backup = Backup::find($this->backupId);
        if ($backup && $backup->status === Backup::STATUS_READY) {
            $backups->restore($backup);
        }
    }
}
