<?php

namespace Database\Factories;

use App\Models\File;
use App\Models\NoteMention;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NoteMention>
 */
class NoteMentionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'file_id' => File::factory(),
            'mentioned_user_id' => User::factory(),
        ];
    }
}
