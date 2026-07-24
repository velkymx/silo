<?php

namespace Tests\Feature\Rss;

use App\Models\RssFeed;
use App\Models\RssItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedStatsTest extends TestCase
{
    use RefreshDatabase;

    public function test_stats_endpoint_returns_aggregate_metrics(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create([
            'enabled' => true,
            'last_success_at' => now()->subMinutes(10),
        ]);
        $now = now();
        RssItem::factory()->count(3)->create([
            'feed_id' => $feed->id,
            'user_id' => $user->id,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        RssItem::factory()->create([
            'feed_id' => $feed->id,
            'user_id' => $user->id,
            'is_read' => false,
            'created_at' => $now->subDays(5),
            'updated_at' => $now->subDays(5),
        ]);

        $response = $this->actingAs($user)->getJson('/rss/stats')->assertOk();

        $response->assertJson([
            'articles_today' => 3,
            'unread_total' => 4,
            'failed_count' => 0,
            'success_rate' => 100,
            'feeds_count' => 1,
        ]);
        $this->assertNotNull($response->json('last_success_at'));
    }

    public function test_failed_feed_lowers_success_rate(): void
    {
        $user = User::factory()->create();
        RssFeed::factory()->for($user)->create(['enabled' => true, 'last_error' => null]);
        RssFeed::factory()->for($user)->create(['enabled' => true, 'last_error' => 'HTTP 500']);
        RssFeed::factory()->for($user)->create(['enabled' => true, 'last_error' => 'timeout']);

        $response = $this->actingAs($user)->getJson('/rss/stats');
        $this->assertSame(2, $response->json('failed_count'));
        $this->assertSame(33, $response->json('success_rate'));
    }

    public function test_stats_exclude_muted_feeds(): void
    {
        $user = User::factory()->create();
        $active = RssFeed::factory()->for($user)->create();
        $muted = RssFeed::factory()->for($user)->create();
        $muted->mute();
        RssItem::factory()->count(2)->create(['feed_id' => $muted->id, 'user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson('/rss/stats');
        $this->assertSame(1, $response->json('feeds_count'));
        $this->assertSame(0, $response->json('unread_total'));
    }

    public function test_avg_frequency_hours_uses_published_at_span(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        RssItem::factory()->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'published_at' => now()->subDays(10)]);
        RssItem::factory()->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'published_at' => now()->subDays(8)]);
        RssItem::factory()->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'published_at' => now()->subDays(6)]);
        RssItem::factory()->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'published_at' => now()->subDays(4)]);
        RssItem::factory()->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'published_at' => now()->subDays(2)]);

        $response = $this->actingAs($user)->getJson('/rss/stats');
        // 8-day span between first and last item / 4 intervals = 48 hours
        $this->assertSame(48, $response->json('avg_frequency_hours'));
    }

    public function test_avg_frequency_null_when_no_feed_has_enough_items(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        RssItem::factory()->create(['feed_id' => $feed->id, 'user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson('/rss/stats');
        $this->assertNull($response->json('avg_frequency_hours'));
    }

    public function test_per_feed_includes_count_last_fetch_and_error(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create(['title' => 'A', 'last_error' => 'timeout']);
        RssItem::factory()->count(5)->create(['feed_id' => $feed->id, 'user_id' => $user->id]);

        $response = $this->actingAs($user)->getJson('/rss/stats');
        $perFeed = collect($response->json('per_feed'));
        $this->assertCount(1, $perFeed);
        $this->assertSame('A', $perFeed[0]['title']);
        $this->assertSame(5, $perFeed[0]['count']);
        $this->assertSame('timeout', $perFeed[0]['last_error']);
    }
}
