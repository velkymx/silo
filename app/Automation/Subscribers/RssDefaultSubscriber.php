<?php

namespace App\Automation\Subscribers;

use App\Automation\Events\AutomationEvent;
use App\Jobs\Automation\Subscribers\IndexItemForSearch;
use App\Jobs\Automation\Subscribers\RecordActivity;
use App\Models\Notification;
use App\Models\RssFeed;
use App\Models\RssItem;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Default RSS infrastructure. Audit runs first (priority 10) so a
 * downstream subscriber failure doesn't drop the activity record;
 * search re-index runs at priority 50. Also owns the built-in,
 * aggregated per-feed notification ("Feed has N new articles") so the
 * bell never floods with one row per article.
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

        if ($event->type === 'rss.feed.fetched') {
            $this->notifyNewItems($event);
        }
    }

    /**
     * One aggregated notification per feed: "«Feed» has N new articles".
     * Repeated fetches coalesce into the existing unread row (count
     * accumulates) instead of stacking one notification per article or
     * per refresh. Muted feeds stay silent.
     */
    private function notifyNewItems(AutomationEvent $event): void
    {
        $newCount = (int) ($event->payload['new_count'] ?? 0);
        if ($newCount < 1) {
            return;
        }

        $feed = RssFeed::find($event->payload['feed_id'] ?? 0);
        if ($feed === null || $feed->muted_at !== null) {
            return;
        }

        $existing = Notification::query()
            ->where('user_id', $event->userId)
            ->where('type', 'rss.feed.new_items')
            ->where('source_id', $feed->id)
            ->where('source_type', RssFeed::class)
            ->whereNull('read_at')
            ->first();

        $total = $newCount + (int) ($existing?->data['count'] ?? 0);
        $title = Str::limit($feed->title, 120)." has {$total} new ".Str::plural('article', $total);

        if ($existing) {
            $existing->update(['title' => $title, 'data' => ['count' => $total]]);

            return;
        }

        Notification::create([
            'user_id' => $event->userId,
            'type' => 'rss.feed.new_items',
            'severity' => Notification::SEVERITY_LOW,
            'title' => $title,
            'url' => '/rss?feed='.$feed->id,
            'data' => ['count' => $total],
            'source_id' => $feed->id,
            'source_type' => RssFeed::class,
        ]);
    }
}
