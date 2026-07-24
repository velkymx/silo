<?php

namespace Database\Factories;

use App\Models\AutomationRule;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AutomationRule>
 */
class AutomationRuleFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'scope' => AutomationRule::SCOPE_PERSONAL,
            'name' => 'Star Laravel security posts',
            'description' => null,
            'enabled' => true,
            'trigger_event' => 'rss.item.created',
            'conditions_json' => [
                'title_contains' => 'security',
                'feed_title_contains' => 'Laravel',
            ],
            'actions_json' => [
                ['type' => 'mark_starred', 'data' => []],
            ],
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['enabled' => false]);
    }

    public function system(): static
    {
        return $this->state(fn () => ['scope' => AutomationRule::SCOPE_SYSTEM, 'user_id' => null]);
    }
}
