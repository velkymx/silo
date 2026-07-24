<?php

namespace App\Workflow;

/**
 * Immutable activity in a workflow. An activity is a unit of work that
 * the runtime executes; the runtime decides *how* (action handler, delay
 * queue, human approval, etc.) based on `type`.
 *
 * Naming convention for ids: `{workflow_slug}.{kind}.{seq}` where seq
 * is the 1-based index in the workflow. The compiler enforces this
 * format so log lines and the visual debugger share the same ids.
 *
 * @phpstan-type ActivityArray array{
 *     id: string,
 *     type: ActivityType,
 *     name?: string,
 *     action?: string,
 *     data?: array<string, mixed>,
 *     next?: string|array<string, string>|null
 * }
 */
final class Activity
{
    /**
     * @param  string|array<string, string>|null  $next  next activity id, or a branch map for DECISION
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly string $id,
        public readonly ActivityType $type,
        public readonly ?string $name = null,
        public readonly ?string $action = null,
        public readonly array $data = [],
        public readonly string|array|null $next = null,
    ) {}

    /**
     * Build from JSON, normalizing legacy rule-actions shape:
     *   { type: "create_notification", data: {...} }  // legacy
     * becomes
     *   { type: "action", action: "create_notification", data: {...} }
     *
     * @param  array<string, mixed>  $raw
     */
    public static function fromArray(array $raw, string $defaultId): self
    {
        $typeRaw = (string) ($raw['type'] ?? '');
        $type = ActivityType::tryFrom($typeRaw);

        // Legacy shorthand: an unknown type is treated as an ACTION.
        if ($type === null) {
            $type = ActivityType::ACTION;
            $action = $typeRaw !== '' ? $typeRaw : null;
        } else {
            $action = isset($raw['action']) ? (string) $raw['action'] : null;
        }

        $id = (string) ($raw['id'] ?? $defaultId);
        $name = isset($raw['name']) ? (string) $raw['name'] : null;
        $data = is_array($raw['data'] ?? null) ? $raw['data'] : [];
        $next = $raw['next'] ?? null;
        if (is_string($next)) {
            // single edge
        } elseif (is_array($next)) {
            $next = array_map('strval', $next);
        } else {
            $next = null;
        }

        return new self(
            id: $id,
            type: $type,
            name: $name,
            action: $action,
            data: $data,
            next: $next,
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'name' => $this->name,
            'action' => $this->action,
            'data' => $this->data,
            'next' => $this->next,
        ];
    }
}
