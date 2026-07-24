<?php

namespace App\Workflow\Steps;

use App\Workflow\Execution\ExecutionContext;

/**
 * A Step is the runtime's view of an Activity. The compiler maps each
 * Activity to exactly one Step; the Runtime invokes Step::run for the
 * current node and follows Activity::next to the next.
 *
 * A Step receives the full ExecutionContext. It may read the resolved
 * context, read/write variables, and append to the execution log. It
 * returns a Result describing what happened. The runtime uses the
 * result to advance, branch, short-circuit, or fail the workflow.
 */
interface Step
{
    public function run(ExecutionContext $context): StepResult;
}
