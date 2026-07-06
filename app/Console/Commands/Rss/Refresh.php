<?php

namespace App\Console\Commands\Rss;

use App\Jobs\Rss\RefreshAllFeeds;
use Illuminate\Console\Command;

/**
 * Hourly scheduler entry point. Dispatching a single job (instead of fanning
 * out synchronously) keeps the scheduler tick sub-second and lets the queue
 * worker rate-limit per feed.
 */
class Refresh extends Command
{
    protected $signature = 'rss:refresh';

    protected $description = 'Queue a refresh for every enabled RSS feed.';

    public function handle(): int
    {
        RefreshAllFeeds::dispatch();
        $this->info('Queued RSS refresh.');

        return self::SUCCESS;
    }
}
