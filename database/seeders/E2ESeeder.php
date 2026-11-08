<?php

namespace Database\Seeders;

use App\Models\RssFeed;
use App\Models\RssItem;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class E2ESeeder extends Seeder
{
    /**
     * Deterministic account + RSS fixture for the end-to-end suite.
     *
     * The RSS item carries feed-controlled remote image URLs so the browser
     * suite can prove they are rewritten to the same-origin image proxy.
     */
    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => 'e2e@example.test'],
            ['name' => 'E2E Admin', 'password' => Hash::make('password'), 'is_admin' => true],
        );

        $feed = RssFeed::firstOrCreate(
            ['url' => 'https://e2e.example.test/feed.xml'],
            [
                'user_id' => $user->id,
                'title' => 'E2E Fixture Feed',
                'site_url' => 'https://e2e.example.test',
                'enabled' => true,
                'refresh_interval_minutes' => 60,
            ],
        );

        // Seeding must not enqueue Scout writes (the E2E app has no search
        // index configured), and the fixture is idempotent across runs.
        RssItem::withoutSyncingToSearch(function () use ($feed, $user): void {
            RssItem::firstOrCreate(
                ['feed_id' => $feed->id, 'guid' => 'e2e-rss-image-fixture'],
                [
                    'user_id' => $user->id,
                    'feed_title' => $feed->title,
                    'title' => 'Fixture article with a remote image',
                    'url' => 'https://e2e.example.test/article',
                    'excerpt' => 'Fixture article used by the image-proxy E2E spec.',
                    'image_url' => 'https://e2e.example.test/featured.png',
                    'content' => '<p>Fixture</p><img src="https://e2e.example.test/inline.png" alt="inline">',
                    'published_at' => now(),
                ],
            );
        });
    }
}
