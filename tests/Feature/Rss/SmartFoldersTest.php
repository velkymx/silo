<?php

namespace Tests\Feature\Rss;

use App\Models\RssFeed;
use App\Models\RssItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmartFoldersTest extends TestCase
{
    use RefreshDatabase;

    public function test_today_filter_shows_only_todays_published_items(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        RssItem::factory()->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'title' => 'Today', 'published_at' => now()->setTime(9, 0)]);
        RssItem::factory()->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'title' => 'Yesterday', 'published_at' => now()->subDay()]);

        $response = $this->actingAs($user)->get('/rss?filter=today')->assertOk();
        $titles = collect($response->original->getData()['page']['props']['items'])->pluck('title');
        $this->assertEqualsCanonicalizing(['Today'], $titles->all());
    }

    public function test_week_filter_shows_last_seven_days(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        RssItem::factory()->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'title' => 'Yesterday', 'published_at' => now()->subDay()]);
        RssItem::factory()->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'title' => 'Last week', 'published_at' => now()->subDays(8)]);

        $response = $this->actingAs($user)->get('/rss?filter=week')->assertOk();
        $titles = collect($response->original->getData()['page']['props']['items'])->pluck('title');
        $this->assertEqualsCanonicalizing(['Yesterday'], $titles->all());
    }

    public function test_recent_filter_uses_created_at_not_published_at(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        RssItem::factory()->create([
            'feed_id' => $feed->id,
            'user_id' => $user->id,
            'title' => 'Old but just added',
            'published_at' => now()->subYear(),
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);
        RssItem::factory()->create([
            'feed_id' => $feed->id,
            'user_id' => $user->id,
            'title' => 'Added long ago',
            'published_at' => now()->subDay(),
            'created_at' => now()->subDays(30),
            'updated_at' => now()->subDays(30),
        ]);

        $response = $this->actingAs($user)->get('/rss?filter=recent')->assertOk();
        $titles = collect($response->original->getData()['page']['props']['items'])->pluck('title');
        $this->assertEqualsCanonicalizing(['Old but just added'], $titles->all());
    }

    public function test_counts_expose_today_week_and_recent(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        RssItem::factory()->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'published_at' => now()->setTime(8, 0), 'created_at' => now()]);
        RssItem::factory()->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'published_at' => now()->subDays(3), 'created_at' => now()]);
        RssItem::factory()->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'published_at' => now()->subDays(10), 'created_at' => now()->subDays(10)]);

        $response = $this->actingAs($user)->get('/rss')->assertOk();
        $counts = $response->original->getData()['page']['props']['counts'];
        $this->assertSame(1, $counts['today']);
        $this->assertSame(2, $counts['week']);
        $this->assertSame(2, $counts['recent']);
    }

    public function test_smart_folders_exclude_muted_feeds(): void
    {
        $user = User::factory()->create();
        $active = RssFeed::factory()->for($user)->create();
        $muted = RssFeed::factory()->for($user)->create();
        $muted->mute();
        RssItem::factory()->create(['feed_id' => $active->id, 'user_id' => $user->id, 'published_at' => now()]);
        RssItem::factory()->create(['feed_id' => $muted->id, 'user_id' => $user->id, 'published_at' => now()]);

        $response = $this->actingAs($user)->get('/rss?filter=today')->assertOk();
        $items = $response->original->getData()['page']['props']['items'];
        $this->assertCount(1, $items);
        $this->assertSame($active->id, $items[0]['feed_id']);
    }
}
