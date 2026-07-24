<?php

namespace App\Automation\Subscribers;

use App\Automation\Events\AutomationEvent;
use App\Automation\Events\AutomationEventRegistry;

/**
 * Registry the dispatcher consults. Modules register their infrastructure
 * subscribers in their own service provider. Order on dispatch is
 * priority ascending — registration order breaks ties.
 */
class SubscriberRegistry
{
    /** @var array<int, PlatformSubscriber> */
    private array $subscribers = [];

    public function __construct(private readonly AutomationEventRegistry $events) {}

    public function register(PlatformSubscriber $subscriber): void
    {
        $this->subscribers[] = $subscriber;
    }

    /** @return array<int, PlatformSubscriber> */
    public function for(AutomationEvent $event): array
    {
        $matched = array_values(array_filter(
            $this->subscribers,
            fn (PlatformSubscriber $s) => $s->subscribesTo() === '*' || $this->events->matches($s->subscribesTo(), $event->type),
        ));

        // Stable sort by priority ascending; preserves insertion order on ties.
        usort($matched, fn (PlatformSubscriber $a, PlatformSubscriber $b) => $a->priority() <=> $b->priority());

        return $matched;
    }

    /** @return array<int, PlatformSubscriber> */
    public function all(): array
    {
        return $this->subscribers;
    }
}
