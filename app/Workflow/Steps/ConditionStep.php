<?php

namespace App\Workflow\Steps;

use App\Workflow\Activity;
use App\Workflow\Execution\ExecutionContext;
use App\Workflow\Expression\ConditionEvaluator;

/**
 * Evaluates a boolean condition over the resolved context. The
 * activity's `data.condition` is a structured condition object
 * (ConditionEvaluator-compatible). true advances to the next activity;
 * false short-circuits the workflow (the runtime records `skipped`).
 */
class ConditionStep implements Step
{
    public function __construct(private readonly ConditionEvaluator $evaluator) {}

    public function run(ExecutionContext $context): StepResult
    {
        $activity = $context->activity;
        if (! $activity instanceof Activity) {
            return StepResult::fail('missing activity in context');
        }
        $condition = $activity->data['condition'] ?? [];
        $result = $this->evaluator->evaluate(is_array($condition) ? $condition : [], $context->context);

        return $result['ok'] ? StepResult::ok() : StepResult::fail('condition not met');
    }
}
