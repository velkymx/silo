<?php

namespace App\Automation\Resolvers;

use App\Automation\Events\AutomationEvent;
use App\Models\RssFeed;
use App\Models\RssItem;
use Illuminate\Support\Str;

/**
 * Maps RSS-flavored events into a context the engine can evaluate. Only the
 * keys listed here are addressable from rule JSON; unknown keys in conditions
 * are ignored (logged).
 */
class RssEventContextResolver implements EventContextResolver
{
    public function resolve(AutomationEvent $event): array
    {
        $context = $event->payload;

        if (isset($context['item_id'])) {
            $item = RssItem::with('feed')->find($context['item_id']);
            if ($item) {
                $context['item'] = $item;
                $context['item_title'] = $item->title;
                $context['item_excerpt'] = $item->excerpt;
                $context['item_author'] = $item->author;
                $context['item_url'] = $item->url;
                $context['item_guid'] = $item->guid;
                $context['item_published_at'] = optional($item->published_at)->toIso8601String();
                $context['feed_id'] = $item->feed_id;
                if ($item->feed) {
                    $context['feed_title'] = $item->feed->title;
                    $context['feed_url'] = $item->feed->url;
                    $context['feed_folder'] = $item->feed->folder;
                }
            }
        }

        if (isset($context['feed_id']) && ! isset($context['feed_title'])) {
            $feedModel = RssFeed::find($context['feed_id']);
            if ($feedModel) {
                $context['feed_title'] = $feedModel->title;
                $context['feed_url'] = $feedModel->url;
                $context['feed_folder'] = $feedModel->folder;
            }
        }

        // Normalize type prefixes so condition authors can use either form.
        $context['event_type'] = $event->type;
        $context['event_namespace'] = Str::before($event->type, '.');

        return $context;
    }
}
