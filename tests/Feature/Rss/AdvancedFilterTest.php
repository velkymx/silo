<?php

namespace Tests\Feature\Rss;

use App\Models\RssFeed;
use App\Models\RssItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvancedFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_author_filter_narrows_by_partial_match(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        RssItem::factory()->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'title' => 'A', 'author' => 'Jane Doe']);
        RssItem::factory()->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'title' => 'B', 'author' => 'John Smith']);

        $response = $this->actingAs($user)->get('/rss?author=Jane')->assertOk();
        $titles = collect($response->original->getData()['page']['props']['items'])->pluck('title');
        $this->assertEqualsCanonicalizing(['A'], $titles->all());
    }

    public function test_exclude_filter_drops_matching_items(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        RssItem::factory()->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'title' => 'Crypto news', 'excerpt' => 'bitcoin rises']);
        RssItem::factory()->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'title' => 'Laravel tips', 'excerpt' => 'policies explained']);

        $response = $this->actingAs($user)->get('/rss?exclude=Crypto')->assertOk();
        $titles = collect($response->original->getData()['page']['props']['items'])->pluck('title');
        $this->assertEqualsCanonicalizing(['Laravel tips'], $titles->all());
    }

    public function test_exclude_works_in_excerpt_too(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        RssItem::factory()->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'title' => 'Good news', 'excerpt' => 'sponsored content here']);
        RssItem::factory()->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'title' => 'Other news', 'excerpt' => 'real reporting']);

        $response = $this->actingAs($user)->get('/rss?exclude=sponsored')->assertOk();
        $titles = collect($response->original->getData()['page']['props']['items'])->pluck('title');
        $this->assertEqualsCanonicalizing(['Other news'], $titles->all());
    }

    public function test_search_and_exclude_combine(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        RssItem::factory()->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'title' => 'Laravel crypto', 'excerpt' => 'tips']);
        RssItem::factory()->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'title' => 'Laravel news', 'excerpt' => 'release notes']);
        RssItem::factory()->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'title' => 'Symfony tips', 'excerpt' => 'general']);

        $response = $this->actingAs($user)->get('/rss?search=Laravel&exclude=crypto')->assertOk();
        $titles = collect($response->original->getData()['page']['props']['items'])->pluck('title');
        $this->assertEqualsCanonicalizing(['Laravel news'], $titles->all());
    }
}
