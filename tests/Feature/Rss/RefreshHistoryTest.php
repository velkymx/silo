<?php

namespace Tests\Feature\Rss;

use App\Models\RssFeed;
use App\Models\RssItem;
use App\Models\RssRefreshLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RefreshHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_stats_endpoint_includes_refresh_history(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        RssRefreshLog::create([
            'rss_feed_id' => $feed->id,
            'user_id' => $user->id,
            'started_at' => now()->subHour(),
            'completed_at' => now()->subHour()->addSecond(),
            'http_status' => 200,
            'response_time_ms' => 250,
            'outcome' => RssRefreshLog::OUTCOME_SUCCESS,
            'new_items_count' => 3,
        ]);
        RssRefreshLog::create([
            'rss_feed_id' => $feed->id,
            'user_id' => $user->id,
            'started_at' => now()->subMinutes(30),
            'completed_at' => now()->subMinutes(30)->addSecond(),
            'http_status' => 500,
            'response_time_ms' => 100,
            'outcome' => RssRefreshLog::OUTCOME_HTTP_ERROR,
            'new_items_count' => 0,
            'error' => 'HTTP 500',
        ]);

        $response = $this->actingAs($user)->getJson('/rss/stats')->assertOk();
        $history = $response->json('refresh_history');
        $this->assertCount(2, $history);
        // Order is DESC by started_at, so the subMinutes(30) row comes first.
        $this->assertSame(100, $history[0]['response_time_ms']);
        $this->assertSame(500, $history[0]['http_status']);
        $this->assertSame('HTTP 500', $history[0]['outcome_label']);
        $this->assertSame(250, $history[1]['response_time_ms']);
        $this->assertSame(200, $history[1]['http_status']);
        $this->assertSame('OK', $history[1]['outcome_label']);
    }

    public function test_longest_outage_computes_window_between_successes(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();

        // Failure at -120 min, recovery at -30 min — outage = 90 min
        RssRefreshLog::create([
            'rss_feed_id' => $feed->id,
            'user_id' => $user->id,
            'started_at' => now()->subMinutes(120),
            'outcome' => RssRefreshLog::OUTCOME_HTTP_ERROR,
        ]);
        RssRefreshLog::create([
            'rss_feed_id' => $feed->id,
            'user_id' => $user->id,
            'started_at' => now()->subMinutes(30),
            'outcome' => RssRefreshLog::OUTCOME_SUCCESS,
        ]);

        $response = $this->actingAs($user)->getJson('/rss/stats');
        $this->assertSame(90, $response->json('longest_outage_minutes'));
    }

    public function test_longest_outage_zero_when_no_failures(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        RssRefreshLog::create([
            'rss_feed_id' => $feed->id,
            'user_id' => $user->id,
            'started_at' => now()->subHour(),
            'outcome' => RssRefreshLog::OUTCOME_SUCCESS,
        ]);

        $response = $this->actingAs($user)->getJson('/rss/stats');
        $this->assertSame(0, $response->json('longest_outage_minutes'));
    }

    public function test_items_per_day_buckets_30_days(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        $today = now()->startOfDay();
        RssItem::factory()->count(3)->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'created_at' => $today]);
        RssItem::factory()->count(2)->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'created_at' => $today->copy()->subDays(2)]);

        $response = $this->actingAs($user)->getJson('/rss/stats');
        $perDay = $response->json('items_per_day');
        $this->assertSame(3, $perDay[$today->format('Y-m-d')]);
        $this->assertSame(2, $perDay[$today->copy()->subDays(2)->format('Y-m-d')]);
    }
}
