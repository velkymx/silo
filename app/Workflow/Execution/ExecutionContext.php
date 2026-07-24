<?php

namespace App\Workflow\Execution;

use App\Automation\Events\AutomationEvent;
use App\Models\AutomationRule;
use App\Workflow\Activity;

/**
 * The single object passed to every Step. v1 carries only what the
 * linear condition→actions→end pipeline needs:
 *
 *   - event      the originating event
 *   - rule       the AutomationRule being executed
 *   - activity   the activity the step is running (null for the start)
 *   - context    the resolver-hydrated event context (read-only)
 *
 * v1 has no mutable cross-step state. When workflows grow
 * variables / metadata / log, add them here.
 */
final class ExecutionContext
{
    /**
     * @param  array<string, mixed>  $context  resolved by the dispatcher
     */
    public function __construct(
        public readonly AutomationEvent $event,
        public readonly AutomationRule $rule,
        public readonly ?Activity $activity,
        public readonly array $context,
    ) {}

    public static function start(AutomationEvent $event, AutomationRule $rule, array $context): self
    {
        return new self($event, $rule, null, $context);
    }

    /** Fork a new context for the next activity, replacing the current one. */
    public function withActivity(Activity $activity): self
    {
        return new self($this->event, $this->rule, $activity, $this->context);
    }
}
