<?php

namespace Database\Factories;

use App\Models\File;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<File>
 */
class FileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word().'.txt';

        return [
            'name' => $name,
            'path' => 'uploads/'.fake()->uuid().'/'.$name,
            'disk' => 'public',
            'is_dir' => false,
            'mime' => 'text/plain',
            'size' => fake()->numberBetween(1, 5000),
            'hash' => hash('sha256', fake()->sentence()),
            'parent_id' => null,
            'owner_id' => User::factory(),
        ];
    }

    /**
     * Indicate that the model is a folder.
     */
    public function folder(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_dir' => true,
            'name' => fake()->unique()->word(),
            'mime' => null,
            'size' => 0,
            'hash' => null,
        ]);
    }
}
