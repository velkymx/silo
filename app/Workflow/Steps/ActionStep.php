<?php

namespace App\Workflow\Steps;

use App\Automation\Actions\ActionRegistry;
use App\Workflow\Activity;
use App\Workflow\Execution\ExecutionContext;

/**
 * Wraps a registered ActionHandler. Looks up the activity's named
 * `action` in the registry, calls `execute(rule, data, context)`.
 *
 * Failures bubble up as STATUS_FAIL. The runtime continues to the next
 * activity (errors are isolated per-step, not per-workflow).
 */
class ActionStep implements Step
{
    public function __construct(private readonly ActionRegistry $actions) {}

    public function run(ExecutionContext $context): StepResult
    {
        $activity = $context->activity;
        if (! $activity instanceof Activity) {
            return StepResult::fail('missing activity in context');
        }
        $actionName = $activity->action;
        $data = $activity->data;

        if (! $actionName || ! $this->actions->has($actionName)) {
            return StepResult::fail("unknown action: {$actionName}");
        }

        try {
            $this->actions->get($actionName)->execute($context->rule, $data, $context->context);

            return StepResult::ok();
        } catch (\Throwable $e) {
            return StepResult::fail($e->getMessage());
        }
    }
}
