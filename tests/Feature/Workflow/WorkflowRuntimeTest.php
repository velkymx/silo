<?php

namespace Tests\Feature\Workflow;

use App\Automation\Actions\ActionRegistry;
use App\Automation\Events\AutomationEvent;
use App\Models\AutomationRule;
use App\Workflow\Activity;
use App\Workflow\ActivityType;
use App\Workflow\Execution\ExecutionContext;
use App\Workflow\Expression\ConditionEvaluator;
use App\Workflow\Runtime\WorkflowRuntime;
use App\Workflow\Steps\Step;
use App\Workflow\Steps\StepRegistry;
use App\Workflow\Steps\StepResult;
use Tests\TestCase;

class WorkflowRuntimeTest extends TestCase
{
    public function test_runs_condition_then_action_then_end(): void
    {
        $registry = $this->registryWithActionOverride(new class implements Step
        {
            public function run(ExecutionContext $context): StepResult
            {
                return StepResult::ok();
            }
        });
        $runtime = new WorkflowRuntime($registry);

        $activities = $this->build([
            ['id' => 'a', 'type' => 'condition', 'data' => ['condition' => ['title_contains' => 'php']], 'next' => 'b'],
            ['id' => 'b', 'type' => 'action', 'action' => 'noop', 'data' => [], 'next' => 'c'],
            ['id' => 'c', 'type' => 'end'],
        ]);

        $result = $runtime->run(
            'a',
            $activities,
            new AutomationRule,
            new AutomationEvent('rss.item.created', 1, []),
            ['item_title' => 'Hello PHP world'],
        );

        $this->assertSame('matched', $result['status']);
    }

    public function test_skips_when_condition_fails(): void
    {
        $registry = $this->registryWithActionOverride(new class implements Step
        {
            public function run(ExecutionContext $context): StepResult
            {
                throw new \RuntimeException('action should not run');
            }
        });
        $runtime = new WorkflowRuntime($registry);

        $activities = $this->build([
            ['id' => 'a', 'type' => 'condition', 'data' => ['condition' => ['title_contains' => 'php']], 'next' => 'b'],
            ['id' => 'b', 'type' => 'action', 'action' => 'noop', 'data' => [], 'next' => 'c'],
            ['id' => 'c', 'type' => 'end'],
        ]);

        $result = $runtime->run(
            'a',
            $activities,
            new AutomationRule,
            new AutomationEvent('rss.item.created', 1, []),
            ['item_title' => 'unrelated'],
        );

        $this->assertSame('skipped', $result['status']);
        $this->assertSame([], $result['actions']);
    }

    public function test_fails_when_action_throws(): void
    {
        $registry = $this->registryWithActionOverride(new class implements Step
        {
            public function run(ExecutionContext $context): StepResult
            {
                throw new \RuntimeException('boom');
            }
        });
        $runtime = new WorkflowRuntime($registry);

        $activities = $this->build([
            ['id' => 'a', 'type' => 'condition', 'data' => ['condition' => []], 'next' => 'b'],
            ['id' => 'b', 'type' => 'action', 'action' => 'boom', 'data' => [], 'next' => 'c'],
            ['id' => 'c', 'type' => 'end'],
        ]);

        $result = $runtime->run(
            'a',
            $activities,
            new AutomationRule,
            new AutomationEvent('rss.item.created', 1, []),
            [],
        );

        $this->assertSame('failed', $result['status']);
        $this->assertStringContainsString('boom', (string) $result['error']);
    }

    public function test_unknown_activity_in_chain_fails(): void
    {
        $registry = new StepRegistry(new ConditionEvaluator, new ActionRegistry);
        $runtime = new WorkflowRuntime($registry);

        $activities = $this->build([
            ['id' => 'a', 'type' => 'condition', 'data' => ['condition' => []], 'next' => 'missing'],
            ['id' => 'end', 'type' => 'end'],
        ]);

        $result = $runtime->run(
            'a',
            $activities,
            new AutomationRule,
            new AutomationEvent('rss.item.created', 1, []),
            [],
        );

        $this->assertSame('failed', $result['status']);
        $this->assertStringContainsString('missing', (string) $result['error']);
    }

    private function registryWithActionOverride(Step $override): StepRegistry
    {
        return new class($override) extends StepRegistry
        {
            public function __construct(private readonly Step $override)
            {
                parent::__construct(new ConditionEvaluator, new ActionRegistry);
            }

            public function for(ActivityType $type): Step
            {
                return $type === ActivityType::ACTION ? $this->override : parent::for($type);
            }
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<string, Activity>
     */
    private function build(array $rows): array
    {
        $activities = [];
        foreach ($rows as $row) {
            $activities[$row['id']] = Activity::fromArray($row, $row['id']);
        }

        return $activities;
    }
}
