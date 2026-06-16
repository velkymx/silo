<?php

namespace App\Console\Commands;

use App\Models\Backup;
use App\Services\BackupService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('backup:restore {id : The backup id to restore} {--force : Skip the confirmation prompt}')]
#[Description('Restore the database and files from a backup archive (DESTRUCTIVE)')]
class RestoreBackupCommand extends Command
{
    public function handle(BackupService $backups): int
    {
        $backup = Backup::find($this->argument('id'));
        if (! $backup || $backup->status !== Backup::STATUS_READY) {
            $this->error('No ready backup with that id.');

            return self::FAILURE;
        }

        $this->warn("This OVERWRITES the current database and files with “{$backup->filename}”.");
        if (! $this->option('force') && ! $this->confirm('Continue?')) {
            $this->info('Aborted.');

            return self::SUCCESS;
        }

        $summary = $backups->restore($backup);
        $restored = collect($summary['disks'] ?? [])->sum();
        $this->info("Restored database + {$restored} file(s) from {$backup->filename}.");

        return self::SUCCESS;
    }
}
