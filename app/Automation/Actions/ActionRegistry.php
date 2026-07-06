<?php

namespace App\Automation\Actions;

use InvalidArgumentException;

class ActionRegistry
{
    /** @var array<string, ActionHandler> */
    private array $handlers = [];

    public function __construct()
    {
        foreach ([
            new CreateNotificationAction,
            new TagItemAction,
            new MarkStarredAction,
            new SaveBookmarkAction,
        ] as $handler) {
            $this->register($handler);
        }
    }

    public function register(ActionHandler $handler): void
    {
        $this->handlers[$handler->type()] = $handler;
    }

    public function has(string $type): bool
    {
        return isset($this->handlers[$type]);
    }

    public function get(string $type): ActionHandler
    {
        if (! isset($this->handlers[$type])) {
            throw new InvalidArgumentException("Unknown action type: {$type}");
        }

        return $this->handlers[$type];
    }

    /** @return array<int, string> */
    public function types(): array
    {
        return array_keys($this->handlers);
    }
}
