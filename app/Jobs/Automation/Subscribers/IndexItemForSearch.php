<?php

namespace App\Jobs\Automation\Subscribers;

use App\Models\RssItem;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Touches an RssItem so the search driver (Scout/Meilisearch/DB) re-indexes
 * it. Lives on the queue so the search backend never blocks the worker
 * that ingested the event.
 */
class IndexItemForSearch implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(public int $itemId) {}

    public function handle(): void
    {
        $item = RssItem::find($this->itemId);
        if (! $item) {
            return;
        }
        $item->touch();
    }
}
