<?php

namespace App\Workflow\Steps;

use App\Workflow\Execution\ExecutionContext;

/**
 * Terminates the workflow. Always returns ok; the runtime stops on END.
 */
class EndStep implements Step
{
    public function run(ExecutionContext $context): StepResult
    {
        return StepResult::ok();
    }
}
