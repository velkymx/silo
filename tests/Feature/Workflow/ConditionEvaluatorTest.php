<?php

namespace Tests\Feature\Workflow;

use App\Workflow\Expression\ConditionEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConditionEvaluatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_string_contains_matches_across_context(): void
    {
        $evaluator = new ConditionEvaluator;
        $result = $evaluator->evaluate(
            ['title_contains' => 'security', 'feed_title_contains' => 'Laravel'],
            [
                'item_title' => 'Critical security update',
                'feed_title' => 'Laravel News',
            ],
        );

        $this->assertTrue($result['ok']);
    }

    public function test_unknown_key_is_a_no_op(): void
    {
        $evaluator = new ConditionEvaluator;
        $result = $evaluator->evaluate(['mystery_key' => 'x'], []);

        $this->assertTrue($result['ok']);
    }

    public function test_feed_id_equality(): void
    {
        $evaluator = new ConditionEvaluator;
        $matched = $evaluator->evaluate(['feed_id' => 42], ['feed_id' => 42]);
        $missed = $evaluator->evaluate(['feed_id' => 7], ['feed_id' => 42]);

        $this->assertTrue($matched['ok']);
        $this->assertFalse($missed['ok']);
    }

    public function test_empty_conditions_match(): void
    {
        $evaluator = new ConditionEvaluator;
        $this->assertTrue($evaluator->evaluate([], [])['ok']);
    }
}
