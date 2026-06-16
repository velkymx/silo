<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('backup:run')]
#[Description('Create a compressed backup archive of the database and stored files')]
class RunBackupCommand extends Command
{
    public function handle(BackupService $backups): int
    {
        $backup = $backups->create();

        if ($backup->status !== \App\Models\Backup::STATUS_READY) {
            $this->error("Backup failed: {$backup->note}");

            return self::FAILURE;
        }

        $this->info("Backup created: {$backup->filename} ({$this->human($backup->size)}, {$backup->compression}).");

        return self::SUCCESS;
    }

    private function human(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 1).' '.$units[$i];
    }
}
