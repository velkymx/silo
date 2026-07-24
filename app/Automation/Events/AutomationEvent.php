<?php

namespace App\Automation\Events;

use Illuminate\Support\Carbon;

/**
 * The single event shape every producer + subscriber + rule operates on.
 * The dispatcher is the only place that constructs these; producers
 * just hand over a string type + payload.
 *
 * Naming convention: dotted, lowercase, plural source + singular
 * subject + past-tense verb. Examples: rss.item.created, calendar.event.
 * updated, file.uploaded, photo.imported. The dotted form enables
 * wildcard matching later ("rss.item.*", "calendar.*").
 */
final class AutomationEvent
{
    /**
     * @param  array<string, mixed>  $payload  business data — keep small
     * @param  array<string, mixed>  $metadata  infrastructure fields (correlation_id, request_id, …)
     */
    public function __construct(
        public readonly string $type,
        public readonly int $userId,
        public readonly array $payload,
        public readonly ?Carbon $occurredAt = null,
        public readonly ?string $key = null,
        public readonly EventOrigin $origin = EventOrigin::WEB,
        public readonly array $metadata = [],
    ) {}

    public static function make(
        string $type,
        int $userId,
        array $payload = [],
        ?string $key = null,
        ?Carbon $occurredAt = null,
        EventOrigin $origin = EventOrigin::WEB,
        array $metadata = [],
    ): self {
        return new self(
            type: $type,
            userId: $userId,
            payload: $payload,
            occurredAt: $occurredAt ?? now(),
            key: $key,
            origin: $origin,
            metadata: $metadata,
        );
    }

    /** Idempotency key. Producers can override; otherwise we derive. */
    public function idempotencyKey(): string
    {
        if ($this->key) {
            return $this->key;
        }
        $item = $this->payload['item_id'] ?? $this->payload['id'] ?? null;
        $feed = $this->payload['feed_id'] ?? $this->payload['source_id'] ?? null;
        $phase = $this->payload['phase'] ?? null;

        if ($item !== null) {
            return "{$this->type}:item:{$item}".($phase ? ":{$phase}" : '');
        }
        if ($feed !== null) {
            return "{$this->type}:source:{$feed}:".($this->payload['fetched_at'] ?? $this->occurredAt?->timestamp ?? time());
        }

        return $this->type.':'.sha1(json_encode($this->payload) ?: $this->type);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'user_id' => $this->userId,
            'origin' => $this->origin->value,
            'occurred_at' => $this->occurredAt?->toIso8601String(),
            'metadata' => $this->metadata,
            'payload' => $this->payload,
        ];
    }
}
