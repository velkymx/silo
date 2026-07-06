<?php

namespace Database\Factories;

use App\Models\WorkflowTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkflowTemplate>
 */
class WorkflowTemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(3),
            'name' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'icon' => 'lightning-charge-fill',
            'trigger_event' => 'rss.item.created',
            'conditions_json' => ['title_contains' => 'security'],
            'actions_json' => [
                ['type' => 'mark_starred', 'data' => []],
            ],
            'sort_order' => 0,
        ];
    }
}
