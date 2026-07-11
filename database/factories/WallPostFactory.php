<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\WallPost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WallPost>
 */
class WallPostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'wall_user_id' => null, // dashboard wall by default
            'author_id' => User::factory(),
            'body' => '<p>'.fake()->sentence().'</p>',
        ];
    }

    /** A post on a specific user's profile wall. */
    public function onWallOf(User $user): static
    {
        return $this->state(fn () => ['wall_user_id' => $user->id]);
    }
}
