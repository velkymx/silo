<?php

namespace App\Automation\Events;

/**
 * Each producer module registers its event types. The engine never
 * hard-codes a list — the registry is the single source of truth used
 * by the rule editor, the docs, and the wildcard matcher.
 */
class AutomationEventRegistry
{
    /** @var array<string, EventDescriptor> */
    private array $byType = [];

    public function register(string $type, string $category, string $description): void
    {
        $this->byType[$type] = new EventDescriptor($type, $category, $description);
    }

    public function has(string $type): bool
    {
        return isset($this->byType[$type]);
    }

    public function get(string $type): ?EventDescriptor
    {
        return $this->byType[$type] ?? null;
    }

    /** @return array<int, EventDescriptor> */
    public function list(): array
    {
        return array_values($this->byType);
    }

    /** @return array<string, array<int, EventDescriptor>> */
    public function groupedByCategory(): array
    {
        $groups = [];
        foreach ($this->byType as $descriptor) {
            $groups[$descriptor->category][] = $descriptor;
        }
        ksort($groups);

        return $groups;
    }

    /** Wildcard matcher: 'rss.item.*' matches 'rss.item.created', etc. */
    public function matches(string $pattern, string $type): bool
    {
        if ($pattern === $type) {
            return true;
        }
        if (! str_contains($pattern, '*')) {
            return false;
        }
        $regex = '/^'.str_replace(['\\*', '\\.'], ['.*', '\\.'], preg_quote($pattern, '/')).'$/';

        return (bool) preg_match($regex, $type);
    }
}
