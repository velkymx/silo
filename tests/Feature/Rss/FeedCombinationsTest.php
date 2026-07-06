<?php

namespace Tests\Feature\Rss;

use App\Models\RssFeed;
use App\Models\RssItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedCombinationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_feeds_param_restricts_to_listed_feeds(): void
    {
        $user = User::factory()->create();
        $a = RssFeed::factory()->for($user)->create(['title' => 'A']);
        $b = RssFeed::factory()->for($user)->create(['title' => 'B']);
        $c = RssFeed::factory()->for($user)->create(['title' => 'C']);
        RssItem::factory()->create(['feed_id' => $a->id, 'user_id' => $user->id, 'title' => 'a-item']);
        RssItem::factory()->create(['feed_id' => $b->id, 'user_id' => $user->id, 'title' => 'b-item']);
        RssItem::factory()->create(['feed_id' => $c->id, 'user_id' => $user->id, 'title' => 'c-item']);

        $response = $this->actingAs($user)->get("/rss?feeds={$a->id},{$c->id}")->assertOk();
        $titles = collect($response->original->getData()['page']['props']['items'])->pluck('title');
        $this->assertEqualsCanonicalizing(['a-item', 'c-item'], $titles->all());
    }

    public function test_feeds_param_combines_with_smart_folder(): void
    {
        $user = User::factory()->create();
        $a = RssFeed::factory()->for($user)->create(['title' => 'A']);
        $b = RssFeed::factory()->for($user)->create(['title' => 'B']);
        RssItem::factory()->create(['feed_id' => $a->id, 'user_id' => $user->id, 'title' => 'a-unread']);
        RssItem::factory()->create(['feed_id' => $a->id, 'user_id' => $user->id, 'title' => 'a-read', 'is_read' => true]);
        RssItem::factory()->create(['feed_id' => $b->id, 'user_id' => $user->id, 'title' => 'b-unread']);

        $response = $this->actingAs($user)->get("/rss?feeds={$a->id}&filter=unread")->assertOk();
        $titles = collect($response->original->getData()['page']['props']['items'])->pluck('title');
        $this->assertEqualsCanonicalizing(['a-unread'], $titles->all());
    }

    public function test_empty_feeds_param_is_ignored(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        RssItem::factory()->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'title' => 'x']);

        $response = $this->actingAs($user)->get('/rss?feeds=')->assertOk();
        $this->assertCount(1, $response->original->getData()['page']['props']['items']);
    }

    public function test_non_numeric_feeds_param_entries_are_skipped(): void
    {
        $user = User::factory()->create();
        $a = RssFeed::factory()->for($user)->create();
        RssItem::factory()->create(['feed_id' => $a->id, 'user_id' => $user->id, 'title' => 'a-item']);

        $response = $this->actingAs($user)->get("/rss?feeds=foo,{$a->id},bar")->assertOk();
        $this->assertCount(1, $response->original->getData()['page']['props']['items']);
    }

    public function test_feeds_param_does_not_leak_other_users_feeds(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $mine = RssFeed::factory()->for($me)->create();
        $theirs = RssFeed::factory()->for($other)->create();
        RssItem::factory()->create(['feed_id' => $mine->id, 'user_id' => $me->id, 'title' => 'mine']);
        RssItem::factory()->create(['feed_id' => $theirs->id, 'user_id' => $other->id, 'title' => 'theirs']);

        $response = $this->actingAs($me)->get("/rss?feeds={$theirs->id}")->assertOk();
        $this->assertSame([], $response->original->getData()['page']['props']['items']);
    }
}
