<?php

namespace Database\Factories;

use App\Models\AutomationRule;
use App\Models\AutomationRuleExecution;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AutomationRuleExecution>
 */
class AutomationRuleExecutionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'rule_id' => AutomationRule::factory(),
            'user_id' => User::factory(),
            'trigger_event' => 'rss.item.created',
            'occurred_at' => now(),
            'event_key' => fake()->uuid(),
            'event_type' => 'rss.item.created',
            'conditions_evaluated' => ['title_contains' => ['matched' => true, 'value' => 'security']],
            'actions_executed' => [['type' => 'mark_starred', 'ok' => true]],
            'status' => AutomationRuleExecution::STATUS_MATCHED,
            'error' => null,
        ];
    }
}
