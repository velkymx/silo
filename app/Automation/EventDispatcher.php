<?php

namespace App\Automation;

use App\Automation\Events\EventOrigin;
use Carbon\Carbon;

/**
 * The automation engine's surface. RSS (and any future ingestion
 * source) calls only into this dispatcher; rules, conditions,
 * subscribers, and action handlers are an implementation detail.
 */
interface EventDispatcher
{
    /**
     * @param  array<string, mixed>  $payload  business data; keep small (resolvers hydrate the rest)
     * @param  array<string, mixed>  $metadata  infrastructure (correlation_id, request_id, …)
     */
    public function dispatch(
        string $type,
        int $userId,
        array $payload = [],
        ?string $key = null,
        ?Carbon $occurredAt = null,
        EventOrigin $origin = EventOrigin::WEB,
        array $metadata = [],
    ): void;
}
