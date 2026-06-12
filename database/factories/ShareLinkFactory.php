<?php

namespace Database\Factories;

use App\Models\File;
use App\Models\ShareLink;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ShareLink>
 */
class ShareLinkFactory extends Factory
{
    public function definition(): array
    {
        return [
            'file_id' => File::factory(),
            'token' => Str::random(40),
            'allow_download' => true,
            'password' => null,
            'expires_at' => null,
            'created_by' => null,
        ];
    }

    public function expired(): static
    {
        return $this->state(fn () => ['expires_at' => now()->subDay()]);
    }

    public function protectedWith(string $password = 'secret'): static
    {
        return $this->state(fn () => ['password' => bcrypt($password)]);
    }

    public function viewOnly(): static
    {
        return $this->state(fn () => ['allow_download' => false]);
    }
}
