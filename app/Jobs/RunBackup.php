<?php

namespace App\Jobs;

use App\Services\BackupService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RunBackup implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public function __construct(public ?int $userId = null)
    {
    }

    public function handle(BackupService $backups): void
    {
        $backups->create($this->userId);
    }
}
