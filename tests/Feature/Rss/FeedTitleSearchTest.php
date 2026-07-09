<?php

namespace Tests\Feature\Rss;

use App\Models\RssFeed;
use App\Models\RssItem;
use App\Models\User;
use App\Services\PlatformSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedTitleSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_item_carries_feed_title_column(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create(['title' => 'Gizmodo']);
        $item = RssItem::factory()->for($feed, 'feed')->create(['user_id' => $user->id]);

        $this->assertSame('Gizmodo', $item->fresh()->feed_title);
    }

    public function test_searchable_array_uses_the_feed_title_column_not_the_relation(): void
    {
        // The production bug: the database Scout driver LIKEs each searchable
        // key as a real column, so feed_title must resolve to a column value —
        // never the derived relation (which has no column and crashes the query).
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create(['title' => 'Gizmodo News']);
        $item = RssItem::factory()->for($feed, 'feed')->create(['user_id' => $user->id]);

        $item = $item->fresh();
        $item->unsetRelation('feed'); // prove it does not touch the relation
        $this->assertSame('Gizmodo News', $item->toSearchableArray()['feed_title']);
    }

    public function test_search_finds_an_item_by_its_feed_name(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create(['title' => 'Gizmodo News']);
        RssItem::factory()->for($feed, 'feed')->create([
            'user_id' => $user->id,
            'title' => 'Some article',
        ]);

        $results = app(PlatformSearch::class)->search($user->id, 'Gizmodo');

        $this->assertNotEmpty($results['rss']);
        $this->assertSame('Gizmodo News', $results['rss'][0]['meta']['feed_title']);
    }

    public function test_renaming_a_feed_updates_its_items_feed_title(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create(['title' => 'Old Name']);
        $item = RssItem::factory()->for($feed, 'feed')->create(['user_id' => $user->id]);

        $this->actingAs($user)->patch("/rss/feeds/{$feed->id}", ['title' => 'New Name'])->assertRedirect();

        $this->assertSame('New Name', $item->fresh()->feed_title);
    }
}
