<?php

namespace Database\Factories;

use App\Models\Bookmark;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bookmark>
 */
class BookmarkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'owner_id' => User::factory(),
            'title' => fake()->unique()->words(2, true),
            'url' => fake()->url(),
            'description' => fake()->optional()->sentence(),
            'icon' => 'link-45deg',
            'color' => null,
            'category' => fake()->randomElement([null, 'Tools', 'Docs', 'HR']),
            'shared' => false,
            'click_count' => 0,
            'sort_order' => 0,
        ];
    }

    /** A company-wide shared bookmark. */
    public function shared(): static
    {
        return $this->state(fn () => ['shared' => true]);
    }
}
