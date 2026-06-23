<?php

namespace Database\Factories;

use App\Models\File;
use App\Models\NoteLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NoteLink>
 */
class NoteLinkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->words(2, true);

        return [
            'source_file_id' => File::factory(),
            'target_file_id' => null,
            'target_title' => $title,
            'link_text' => $title,
            'owner_id' => User::factory(),
        ];
    }
}
