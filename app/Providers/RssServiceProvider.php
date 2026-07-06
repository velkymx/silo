<?php

namespace App\Providers;

use App\Automation\Events\AutomationEventRegistry;
use App\Automation\Subscribers\RssDefaultSubscriber;
use App\Automation\Subscribers\SubscriberRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * RssServiceProvider — RSS is the first module to register with the
 * automation engine. Future modules (Files, Calendar, Photos, …) will
 * each get their own provider; the engine itself stays oblivious to the
 * list.
 *
 * Each provider does three things:
 *   1. Register its event types in AutomationEventRegistry (with a
 *      category so the rule editor can group them).
 *   2. Register its platform subscribers (search, activity, …).
 *   3. Optionally bind its own resolver.
 */
class RssServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->resolving(AutomationEventRegistry::class, function ($registry) {
            $registry->register('rss.feed.fetched', 'RSS', 'A scheduled RSS feed fetch completed (new or 304).');
            $registry->register('rss.item.created', 'RSS', 'A new RSS article was discovered.');
            $registry->register('rss.item.read', 'RSS', 'A user marked an RSS article as read.');
            $registry->register('rss.item.starred', 'RSS', 'A user starred an RSS article.');
        });

        $this->app->resolving(SubscriberRegistry::class, function (SubscriberRegistry $registry) {
            $registry->register(new RssDefaultSubscriber);
        });
    }
}
