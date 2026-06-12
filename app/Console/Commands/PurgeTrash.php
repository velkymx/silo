<?php

namespace App\Console\Commands;

use App\Services\TrashService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('trash:purge {--days= : Permanently delete items trashed more than this many days ago}')]
#[Description('Permanently delete old trashed files and their blobs')]
class PurgeTrash extends Command
{
    public function handle(TrashService $trash): int
    {
        $days = (int) ($this->option('days') ?: config('filemanager.trash_retention_days'));

        $count = $trash->purgeOlderThan($days);

        $this->info("Purged {$count} trashed item(s) older than {$days} day(s).");

        return self::SUCCESS;
    }
}
