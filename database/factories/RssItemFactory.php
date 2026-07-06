<?php

namespace Database\Factories;

use App\Models\RssFeed;
use App\Models\RssItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RssItem>
 */
class RssItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'feed_id' => RssFeed::factory(),
            'user_id' => User::factory(),
            'guid' => fake()->uuid(),
            'title' => fake()->sentence(6),
            'content' => '<p>'.fake()->paragraphs(2, true).'</p>',
            'excerpt' => fake()->sentence(),
            'author' => fake()->name(),
            'url' => fake()->url(),
            'published_at' => fake()->dateTimeBetween('-7 days'),
            'is_read' => false,
            'is_starred' => false,
        ];
    }

    public function read(): static
    {
        return $this->state(fn () => ['is_read' => true, 'read_at' => now()]);
    }

    public function starred(): static
    {
        return $this->state(fn () => ['is_starred' => true, 'starred_at' => now()]);
    }
}
