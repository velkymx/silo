<?php

namespace Tests\Unit\Workflow;

use App\Models\AutomationRule;
use App\Workflow\Activity;
use App\Workflow\ActivityType;
use App\Workflow\Compiler\WorkflowCompiler;
use PHPUnit\Framework\TestCase;

class WorkflowCompilerTest extends TestCase
{
    public function test_compiles_minimal_rule_to_condition_then_end(): void
    {
        ['start' => $start, 'activities' => $activities] = $this->compileFromRule([
            'id' => 7,
            'conditions_json' => ['title_contains' => 'php'],
            'actions_json' => [],
        ]);

        $this->assertSame('rule.7.condition.1', $start);
        $first = $activities['rule.7.condition.1'] ?? null;
        $this->assertNotNull($first);
        $this->assertSame(ActivityType::CONDITION, $first->type);
        $this->assertSame('rule.7.end.1', $first->next);
    }

    public function test_compiles_chain_of_actions_in_order(): void
    {
        ['start' => $start, 'activities' => $activities] = $this->compileFromRule([
            'id' => 8,
            'conditions_json' => [],
            'actions_json' => [
                ['type' => 'create_notification', 'data' => ['priority' => 'high']],
                ['type' => 'mark_starred', 'data' => []],
            ],
        ]);

        $this->assertSame('rule.8.condition.1', $start);
        $this->assertSame('rule.8.action.1', $activities['rule.8.condition.1']->next);
        $this->assertSame('rule.8.action.2', $activities['rule.8.action.1']->next);
        $this->assertSame('rule.8.end.1', $activities['rule.8.action.2']->next);

        $a1 = $activities['rule.8.action.1'];
        $this->assertSame(ActivityType::ACTION, $a1->type);
        $this->assertSame('create_notification', $a1->action);
        $this->assertSame(['priority' => 'high'], $a1->data);
    }

    public function test_workflow_always_contains_end_and_condition(): void
    {
        ['activities' => $activities] = $this->compileFromRule([
            'id' => 9,
            'conditions_json' => ['feed_id' => 1],
            'actions_json' => [['type' => 'save_bookmark', 'data' => []]],
        ]);

        $hasCondition = false;
        $hasEnd = false;
        $actionCount = 0;
        foreach ($activities as $a) {
            $hasCondition = $hasCondition || $a->type === ActivityType::CONDITION;
            $hasEnd = $hasEnd || $a->type === ActivityType::END;
            if ($a->type === ActivityType::ACTION) {
                $actionCount++;
            }
        }
        $this->assertTrue($hasCondition, 'workflow must contain a CONDITION activity');
        $this->assertTrue($hasEnd, 'workflow must contain an END activity');
        $this->assertSame(1, $actionCount);
    }

    public function test_workflow_is_linear_with_no_branches(): void
    {
        // v1 forbids branches. Every activity's `next` is either a
        // string (one outgoing edge) or null (END). No arrays.
        ['activities' => $activities] = $this->compileFromRule([
            'id' => 10,
            'conditions_json' => [],
            'actions_json' => [
                ['type' => 'create_notification', 'data' => []],
                ['type' => 'save_bookmark', 'data' => []],
            ],
        ]);

        foreach ($activities as $a) {
            if ($a->type === ActivityType::END) {
                $this->assertNull($a->next, 'END has no outgoing edge');
            } else {
                $this->assertIsString($a->next, "{$a->id} must have a single string `next` (v1 has no branches)");
            }
        }
    }

    /**
     * @return array{start: string, activities: array<string, Activity>}
     */
    private function compileFromRule(array $attrs): array
    {
        $rule = new AutomationRule;
        $rule->forceFill($attrs);

        return (new WorkflowCompiler)->compile($rule, "rule.{$attrs['id']}");
    }
}
