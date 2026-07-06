<?php

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => 'rss.item.created',
            'severity' => Notification::SEVERITY_NORMAL,
            'title' => fake()->sentence(4),
            'body' => fake()->sentence(),
            'url' => fake()->optional(0.5)->url(),
            'data' => [],
        ];
    }

    public function high(): static
    {
        return $this->state(fn () => ['severity' => Notification::SEVERITY_HIGH]);
    }

    public function read(): static
    {
        return $this->state(fn () => ['read_at' => now()]);
    }
}
