<?php

namespace App\Automation\Subscribers;

use App\Automation\Events\AutomationEvent;
use App\Jobs\Automation\Subscribers\IndexItemForSearch;
use App\Jobs\Automation\Subscribers\RecordActivity;
use App\Models\RssItem;
use Illuminate\Support\Facades\Log;

/**
 * Default RSS infrastructure. Audit runs first (priority 10) so a
 * downstream subscriber failure doesn't drop the activity record;
 * search re-index runs at priority 50.
 */
class RssDefaultSubscriber implements PlatformSubscriber
{
    public function subscribesTo(): string
    {
        return 'rss.*';
    }

    public function priority(): int
    {
        return 50;
    }

    public function handle(AutomationEvent $event): void
    {
        Log::info('automation.subscriber.rss', [
            'type' => $event->type,
            'user' => $event->userId,
            'key' => $event->idempotencyKey(),
        ]);

        if ($event->type === 'rss.item.created' && isset($event->payload['item_id'])) {
            RecordActivity::dispatch(
                $event->userId,
                'rss.item.created',
                (int) $event->payload['item_id'],
                RssItem::class,
                [
                    'feed_id' => $event->payload['feed_id'] ?? null,
                    'title' => $event->payload['title'] ?? null,
                    'url' => $event->payload['url'] ?? null,
                ],
                $event->occurredAt,
            );
        }

        if (str_starts_with($event->type, 'rss.item.') && isset($event->payload['item_id'])) {
            IndexItemForSearch::dispatch((int) $event->payload['item_id']);
        }
    }
}
