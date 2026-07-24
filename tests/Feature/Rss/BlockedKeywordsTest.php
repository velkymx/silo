<?php

namespace Tests\Feature\Rss;

use App\Models\RssFeed;
use App\Models\RssItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BlockedKeywordsTest extends TestCase
{
    use RefreshDatabase;

    public function test_blocked_keyword_in_title_drops_item(): void
    {
        $user = User::factory()->create(['blocked_keywords' => ['sponsored']]);
        $feed = RssFeed::factory()->for($user)->create();
        RssItem::factory()->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'title' => 'Sponsored content', 'excerpt' => 'buy now']);
        RssItem::factory()->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'title' => 'Real news', 'excerpt' => 'investigation']);

        $response = $this->actingAs($user)->get('/rss')->assertOk();
        $titles = collect($response->original->getData()['page']['props']['items'])->pluck('title');
        $this->assertEqualsCanonicalizing(['Real news'], $titles->all());
    }

    public function test_blocked_keyword_in_excerpt_drops_item(): void
    {
        $user = User::factory()->create(['blocked_keywords' => ['sponsored']]);
        $feed = RssFeed::factory()->for($user)->create();
        RssItem::factory()->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'title' => 'Hello', 'excerpt' => 'sponsored content here']);
        RssItem::factory()->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'title' => 'Other', 'excerpt' => 'real reporting']);

        $response = $this->actingAs($user)->get('/rss')->assertOk();
        $titles = collect($response->original->getData()['page']['props']['items'])->pluck('title');
        $this->assertEqualsCanonicalizing(['Other'], $titles->all());
    }

    public function test_multiple_blocked_keywords_all_apply(): void
    {
        $user = User::factory()->create(['blocked_keywords' => ['sponsored', 'crypto']]);
        $feed = RssFeed::factory()->for($user)->create();
        RssItem::factory()->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'title' => 'Sponsored']);
        RssItem::factory()->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'title' => 'Crypto news']);
        RssItem::factory()->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'title' => 'Laravel tips']);

        $response = $this->actingAs($user)->get('/rss')->assertOk();
        $titles = collect($response->original->getData()['page']['props']['items'])->pluck('title');
        $this->assertEqualsCanonicalizing(['Laravel tips'], $titles->all());
    }

    public function test_blocked_keywords_are_per_user(): void
    {
        $alice = User::factory()->create(['blocked_keywords' => ['crypto']]);
        $bob = User::factory()->create();
        $aliceFeed = RssFeed::factory()->for($alice)->create();
        $bobFeed = RssFeed::factory()->for($bob)->create();
        RssItem::factory()->create(['feed_id' => $aliceFeed->id, 'user_id' => $alice->id, 'title' => 'Crypto news']);
        RssItem::factory()->create(['feed_id' => $bobFeed->id, 'user_id' => $bob->id, 'title' => 'Crypto news']);

        $this->actingAs($alice)->get('/rss')
            ->assertInertia(fn ($page) => $page->where('items', []));

        $this->actingAs($bob)->get('/rss')
            ->assertInertia(fn ($page) => $page->has('items', 1));
    }

    public function test_no_blocked_keywords_returns_all_items(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        RssItem::factory()->count(3)->create(['feed_id' => $feed->id, 'user_id' => $user->id]);

        $response = $this->actingAs($user)->get('/rss')->assertOk();
        $this->assertCount(3, $response->original->getData()['page']['props']['items']);
    }
}
