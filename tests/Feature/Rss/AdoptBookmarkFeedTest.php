<?php

namespace Tests\Feature\Rss;

use App\Jobs\Rss\AdoptBookmarkFeed;
use App\Models\Bookmark;
use App\Models\RssFeed;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdoptBookmarkFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_feed_from_bookmark_with_feed_url(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $bookmark = Bookmark::create([
            'owner_id' => $user->id,
            'title' => 'Laravel News',
            'url' => 'https://laravel-news.com',
            'feed_url' => 'https://laravel-news.com/feed.xml',
            'category' => 'Tech',
        ]);

        (new AdoptBookmarkFeed($bookmark->id))->handle();

        $feed = RssFeed::where('user_id', $user->id)->first();
        $this->assertNotNull($feed);
        $this->assertSame('https://laravel-news.com/feed.xml', $feed->url);
        $this->assertSame('Laravel News', $feed->title);
        $this->assertSame('Tech', $feed->folder);
        $this->assertSame('https://laravel-news.com', $feed->site_url);
    }

    public function test_idempotent_when_feed_already_exists(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        RssFeed::create([
            'user_id' => $user->id,
            'title' => 'Already subscribed',
            'url' => 'https://laravel-news.com/feed.xml',
        ]);
        $bookmark = Bookmark::create([
            'owner_id' => $user->id,
            'title' => 'Laravel News',
            'url' => 'https://laravel-news.com',
            'feed_url' => 'https://laravel-news.com/feed.xml',
        ]);

        (new AdoptBookmarkFeed($bookmark->id))->handle();

        $this->assertSame(1, RssFeed::where('user_id', $user->id)->count());
    }

    public function test_noop_when_bookmark_has_no_feed_url(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $bookmark = Bookmark::create([
            'owner_id' => $user->id,
            'title' => 'A page',
            'url' => 'https://example.com',
        ]);

        (new AdoptBookmarkFeed($bookmark->id))->handle();

        $this->assertSame(0, RssFeed::where('user_id', $user->id)->count());
    }
}
