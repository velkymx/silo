<?php

namespace Tests\Unit\Workflow;

use App\Automation\Actions\ActionRegistry;
use App\Workflow\ActivityType;
use App\Workflow\Expression\ConditionEvaluator;
use App\Workflow\Steps\StepRegistry;
use PHPUnit\Framework\TestCase;

class StepRegistryTest extends TestCase
{
    public function test_v1_kinds_have_a_step(): void
    {
        $registry = new StepRegistry(new ConditionEvaluator, new ActionRegistry);
        foreach (ActivityType::cases() as $type) {
            $this->assertNotNull(
                $registry->for($type),
                "ActivityType::{$type->value} should have a registered Step",
            );
        }
    }

    public function test_canonical_ordering_is_strict_and_stable(): void
    {
        $order = array_map(fn (ActivityType $t) => $t->order(), ActivityType::cases());
        $sorted = $order;
        sort($sorted);
        $this->assertSame($sorted, $order, 'ActivityType::order() must be monotonically increasing');
    }
}
