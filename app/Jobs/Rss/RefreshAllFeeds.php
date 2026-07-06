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
        RssFeed::query()
            ->where('enabled', true)
            ->whereNull('muted_at')
            ->orderBy('id')
            ->chunkById(200, function ($feeds) use (&$count) {
                foreach ($feeds as $feed) {
                    RefreshFeed::dispatch($feed->id);
                    $count++;
                }
            });

        Log::info('rss.refresh_all.dispatched', ['count' => $count, 'muted_skipped' => true]);
    }
}
