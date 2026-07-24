<?php

namespace Tests\Feature\Rss;

use App\Models\RssFeed;
use App\Models\RssItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BooleanSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_and_intersects_title_and_excerpt(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        RssItem::factory()->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'title' => 'Laravel tips', 'excerpt' => 'php coverage']);
        RssItem::factory()->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'title' => 'Symfony news', 'excerpt' => 'php 8.3']);
        RssItem::factory()->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'title' => 'Other', 'excerpt' => 'unrelated']);

        $response = $this->actingAs($user)->get('/rss?search=Laravel+AND+php')->assertOk();
        $titles = collect($response->original->getData()['page']['props']['items'])->pluck('title');
        $this->assertEqualsCanonicalizing(['Laravel tips'], $titles->all());
    }

    public function test_or_unions(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        RssItem::factory()->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'title' => 'Laravel tips']);
        RssItem::factory()->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'title' => 'Wordpress news']);
        RssItem::factory()->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'title' => 'Other']);

        $response = $this->actingAs($user)->get('/rss?search=Laravel+OR+Wordpress')->assertOk();
        $titles = collect($response->original->getData()['page']['props']['items'])->pluck('title');
        $this->assertEqualsCanonicalizing(['Laravel tips', 'Wordpress news'], $titles->all());
    }

    public function test_not_excludes(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        RssItem::factory()->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'title' => 'Laravel tips']);
        RssItem::factory()->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'title' => 'Laravel wordpress bridge']);

        $response = $this->actingAs($user)->get('/rss?search=Laravel+NOT+wordpress')->assertOk();
        $titles = collect($response->original->getData()['page']['props']['items'])->pluck('title');
        $this->assertEqualsCanonicalizing(['Laravel tips'], $titles->all());
    }

    public function test_parenthesised_or_inside_and(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        RssItem::factory()->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'title' => 'Laravel tips']);
        RssItem::factory()->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'title' => 'Wordpress tips']);
        RssItem::factory()->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'title' => 'Other']);

        $response = $this->actingAs($user)->get('/rss?search=(Laravel+OR+Wordpress)+AND+tips')->assertOk();
        $titles = collect($response->original->getData()['page']['props']['items'])->pluck('title');
        $this->assertEqualsCanonicalizing(['Laravel tips', 'Wordpress tips'], $titles->all());
    }

    public function test_plain_word_still_works_as_flat_or(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        RssItem::factory()->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'title' => 'Laravel']);
        RssItem::factory()->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'title' => 'PHP news']);
        RssItem::factory()->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'title' => 'Other']);

        $response = $this->actingAs($user)->get('/rss?search=Laravel+PHP')->assertOk();
        $titles = collect($response->original->getData()['page']['props']['items'])->pluck('title');
        $this->assertEqualsCanonicalizing(['Laravel', 'PHP news'], $titles->all());
    }

    public function test_search_matches_feed_title(): void
    {
        $user = User::factory()->create();
        $tech = RssFeed::factory()->for($user)->create(['title' => 'TechCrunch']);
        $food = RssFeed::factory()->for($user)->create(['title' => 'Bon Appetit']);
        RssItem::factory()->create(['feed_id' => $tech->id, 'user_id' => $user->id, 'title' => 'A']);
        RssItem::factory()->create(['feed_id' => $food->id, 'user_id' => $user->id, 'title' => 'B']);

        $response = $this->actingAs($user)->get('/rss?search=TechCrunch')->assertOk();
        $titles = collect($response->original->getData()['page']['props']['items'])->pluck('title');
        $this->assertEqualsCanonicalizing(['A'], $titles->all());
    }
}
