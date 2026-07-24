<?php

namespace Tests\Feature\Rss;

use App\Models\RssFeed;
use App\Models\RssItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MostActiveFeedsTest extends TestCase
{
    use RefreshDatabase;

    public function test_filter_shows_items_from_top_5_most_active_feeds(): void
    {
        $user = User::factory()->create();
        $busy = RssFeed::factory()->for($user)->create(['title' => 'Busy']);
        $medium = RssFeed::factory()->for($user)->create(['title' => 'Medium']);
        $quiet = RssFeed::factory()->for($user)->create(['title' => 'Quiet']);
        $older = RssFeed::factory()->for($user)->create(['title' => 'Older']);

        // busy: 10 items, medium: 5, quiet: 2, older: 20 (all old)
        RssItem::factory()->count(10)->create(['feed_id' => $busy->id, 'user_id' => $user->id, 'published_at' => now()->subDays(1)]);
        RssItem::factory()->count(5)->create(['feed_id' => $medium->id, 'user_id' => $user->id, 'published_at' => now()->subDays(2)]);
        RssItem::factory()->count(2)->create(['feed_id' => $quiet->id, 'user_id' => $user->id, 'published_at' => now()->subDays(3)]);
        RssItem::factory()->count(20)->create(['feed_id' => $older->id, 'user_id' => $user->id, 'published_at' => now()->subDays(30)]);

        $response = $this->actingAs($user)->get('/rss?filter=top_feeds')->assertOk();
        $props = $response->original->getData()['page']['props'];
        $items = collect($props['items']);

        // busy + medium + quiet (top 3 by count in the last 7 days) — older excluded by date filter
        $feedIds = $items->pluck('feed_id')->unique()->sort()->values()->all();
        $this->assertEqualsCanonicalizing([$busy->id, $medium->id, $quiet->id], $feedIds);
        $this->assertSame(17, $items->count());
    }

    public function test_filter_excludes_muted_feeds(): void
    {
        $user = User::factory()->create();
        $active = RssFeed::factory()->for($user)->create(['title' => 'Active']);
        $muted = RssFeed::factory()->for($user)->create(['title' => 'Muted']);
        $muted->mute();
        RssItem::factory()->count(10)->create(['feed_id' => $active->id, 'user_id' => $user->id, 'published_at' => now()->subDays(1)]);
        RssItem::factory()->count(50)->create(['feed_id' => $muted->id, 'user_id' => $user->id, 'published_at' => now()->subDays(1)]);

        $response = $this->actingAs($user)->get('/rss?filter=top_feeds')->assertOk();
        $feedIds = collect($response->original->getData()['page']['props']['items'])->pluck('feed_id')->unique()->values()->all();
        $this->assertSame([$active->id], $feedIds);
    }

    public function test_filter_returns_empty_when_no_items_in_window(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        RssItem::factory()->count(3)->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'published_at' => now()->subDays(30)]);

        $response = $this->actingAs($user)->get('/rss?filter=top_feeds')->assertOk();
        $this->assertSame([], $response->original->getData()['page']['props']['items']);
    }
}
