<?php

namespace App\Workflow\Steps;

use App\Automation\Actions\ActionRegistry;
use App\Workflow\ActivityType;
use App\Workflow\Expression\ConditionEvaluator;
use InvalidArgumentException;

/**
 * Maps an ActivityType to the Step that runs it. v1 registers only
 * CONDITION, ACTION, and END. Adding a new kind requires a case in
 * ActivityType, a Step class, and a register() call here.
 */
class StepRegistry
{
    /** @var array<string, Step> */
    private array $steps = [];

    public function __construct(
        ?ConditionEvaluator $evaluator = null,
        ?ActionRegistry $actions = null,
    ) {
        $evaluator ??= app(ConditionEvaluator::class);
        $actions ??= app(ActionRegistry::class);

        $this->register(ActivityType::CONDITION, new ConditionStep($evaluator));
        $this->register(ActivityType::ACTION, new ActionStep($actions));
        $this->register(ActivityType::END, new EndStep);
    }

    public function register(ActivityType $type, Step $step): void
    {
        $this->steps[$type->value] = $step;
    }

    public function for(ActivityType $type): Step
    {
        if (! isset($this->steps[$type->value])) {
            throw new InvalidArgumentException("No step registered for activity type: {$type->value}");
        }

        return $this->steps[$type->value];
    }
}
