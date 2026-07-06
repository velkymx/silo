<?php

namespace App\Automation\Events;

/**
 * Registers an event type with the engine. Each producer module
 * registers its own types in its own service provider — the engine
 * itself never holds a hard-coded list.
 *
 * `category` groups types for the rule editor's dropdown. A future
 * Calendar module will register its types under category "Calendar",
 * Files under "Files", and so on.
 */
class EventDescriptor
{
    public function __construct(
        public readonly string $type,
        public readonly string $category,
        public readonly string $description,
    ) {}
}
