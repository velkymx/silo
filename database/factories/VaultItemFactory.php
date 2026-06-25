<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\VaultItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<VaultItem>
 */
class VaultItemFactory extends Factory
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
            'group_id' => null,
            'name' => fake()->unique()->words(2, true),
            'username' => fake()->userName(),
            'url' => fake()->url(),
            'category' => fake()->randomElement([null, 'Infra', 'SaaS']),
            'secret' => fake()->password(16, 24),
            'notes' => null,
        ];
    }
}
