<?php

namespace App\Workflow\Runtime;

use App\Automation\Events\AutomationEvent;
use App\Models\AutomationRule;
use App\Models\AutomationRuleExecution;
use App\Workflow\Activity;
use App\Workflow\ActivityType;
use App\Workflow\Execution\ExecutionContext;
use App\Workflow\Steps\Step;
use App\Workflow\Steps\StepRegistry;
use App\Workflow\Steps\StepResult;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Linear v1 runtime. Executes a compiled workflow as:
 *
 *   CONDITION → ACTION × N → END
 *
 * Every activity carries its own `next` field (set by the compiler);
 * the runtime simply follows the chain. The CONDITION's `fail` is
 * recorded as a rule-skip (the rule didn't match); any other `fail`
 * records the rule as failed.
 */
class WorkflowRuntime
{
    public function __construct(private readonly StepRegistry $steps) {}

    /**
     * @param  string  $start  first activity id (from the compiler)
     * @param  array<string, Activity>  $activities  id → Activity
     * @param  array<string, mixed>  $context  the resolver-hydrated event context
     * @return array{status: string, conditions: array, actions: array, error: ?string}
     */
    public function run(string $start, array $activities, AutomationRule $rule, AutomationEvent $event, array $context): array
    {
        $cursor = $start;
        $executedActions = [];
        $conditionsEvaluated = [];
        $error = null;
        $status = AutomationRuleExecution::STATUS_MATCHED;
        $maxSteps = max(50, count($activities) * 4);

        $step = 0;
        while ($cursor !== null && $step++ < $maxSteps) {
            $activity = $activities[$cursor] ?? null;
            if (! $activity) {
                $error = "workflow references unknown activity: {$cursor}";
                $status = AutomationRuleExecution::STATUS_FAILED;
                break;
            }
            if ($activity->type->isTerminal()) {
                break;
            }

            $ec = new ExecutionContext($event, $rule, $activity, $context);
            $result = $this->invoke($this->steps->for($activity->type), $ec);

            $this->recordStep($activity, $result, $executedActions, $conditionsEvaluated);

            if ($result->isFail()) {
                if ($activity->type === ActivityType::CONDITION) {
                    return [
                        'status' => AutomationRuleExecution::STATUS_SKIPPED,
                        'conditions' => $conditionsEvaluated,
                        'actions' => [],
                        'error' => null,
                    ];
                }
                $error = $result->error;
                $status = AutomationRuleExecution::STATUS_FAILED;
                break;
            }

            $cursor = $activity->next;
        }

        if ($step >= $maxSteps && $status === AutomationRuleExecution::STATUS_MATCHED) {
            $error = "workflow exceeded {$maxSteps} step budget (likely a cycle)";
            $status = AutomationRuleExecution::STATUS_FAILED;
        }

        return [
            'status' => $status,
            'conditions' => $conditionsEvaluated,
            'actions' => $executedActions,
            'error' => $error,
        ];
    }

    private function invoke(Step $step, ExecutionContext $ec): StepResult
    {
        try {
            return $step->run($ec);
        } catch (Throwable $e) {
            Log::warning('workflow.step.threw', [
                'rule' => $ec->rule->id,
                'activity' => $ec->activity?->id,
                'error' => $e->getMessage(),
            ]);

            return StepResult::fail($e->getMessage());
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $actions
     * @param  array<int, array<string, mixed>>  $conditions
     */
    private function recordStep(Activity $activity, StepResult $result, array &$actions, array &$conditions): void
    {
        if ($activity->type === ActivityType::CONDITION) {
            $conditions = $activity->data['condition'] ?? [];

            return;
        }
        if ($activity->type === ActivityType::ACTION) {
            $row = [
                'activity_id' => $activity->id,
                'type' => $activity->action,
                'ok' => $result->isOk(),
            ];
            if ($result->error) {
                $row['error'] = $result->error;
            }
            $actions[] = $row;
        }
    }
}
