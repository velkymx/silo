<?php

namespace Database\Factories;

use App\Models\File;
use App\Models\FileVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FileVersion>
 */
class FileVersionFactory extends Factory
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
            'file_id' => File::factory(),
            'version' => 1,
            'name' => $name,
            'path' => 'uploads/'.fake()->uuid().'/'.fake()->uuid(),
            'disk' => 'public',
            'mime' => 'text/plain',
            'size' => fake()->numberBetween(1, 5000),
            'hash' => hash('sha256', fake()->sentence()),
            'created_by' => null,
        ];
    }
}
