<?php

namespace Database\Factories;

use App\Models\RssFeed;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RssFeed>
 */
class RssFeedFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->unique()->words(2, true),
            'url' => fake()->url(),
            'site_url' => fake()->url(),
            'description' => fake()->sentence(),
            'enabled' => true,
            'refresh_interval_minutes' => 60,
            'folder' => null,
            'sort_order' => 0,
        ];
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['enabled' => false]);
    }
}
