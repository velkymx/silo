<?php

namespace App\Jobs\Rss;

use App\Models\RssFeed;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Per-hour tick. Fanning out one job per feed keeps the worker's runtime
 * short, allows independent retries, and isolates failures (a single bad
 * feed never blocks the rest).
 */
class RefreshAllFeeds implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    public function handle(): void
    {
        $count = 0;
        $skipped = 0;
        RssFeed::dueForRefresh()
            ->orderBy('id')
            ->chunkById(200, function ($feeds) use (&$count, &$skipped) {
                foreach ($feeds as $feed) {
                    if (! $feed->isDueForRefresh()) {
                        $skipped++;

                        continue;
                    }
                    RefreshFeed::dispatch($feed->id);
                    $count++;
                }
            });

        Log::info('rss.refresh_all.dispatched', ['count' => $count, 'not_due_skipped' => $skipped]);
    }
}
