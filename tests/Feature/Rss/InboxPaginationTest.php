<?php

namespace Tests\Feature\Rss;

use App\Models\RssFeed;
use App\Models\RssItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InboxPaginationTest extends TestCase
{
    use RefreshDatabase;

    public function test_inbox_caps_first_page_at_50(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        RssItem::factory()->count(80)->create([
            'feed_id' => $feed->id,
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get('/rss')->assertOk();
        $props = $response->original->getData()['page']['props'];
        $this->assertCount(50, $props['items']);
        $this->assertNotNull($props['itemsNextCursor']);
    }

    public function test_next_cursor_loads_subsequent_page_without_duplicates(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        RssItem::factory()->count(80)->create([
            'feed_id' => $feed->id,
            'user_id' => $user->id,
        ]);

        $first = $this->actingAs($user)->get('/rss')->assertOk();
        $firstCursor = $first->original->getData()['page']['props']['itemsNextCursor'];

        $second = $this->actingAs($user)->get("/rss?cursor={$firstCursor}")->assertOk();
        $firstIds = collect($first->original->getData()['page']['props']['items'])->pluck('id');
        $secondIds = collect($second->original->getData()['page']['props']['items'])->pluck('id');
        $this->assertCount(50, $firstIds);
        $this->assertCount(30, $secondIds);
        $this->assertEmpty($firstIds->intersect($secondIds));
    }

    public function test_next_cursor_is_null_when_fewer_than_50_items(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        RssItem::factory()->count(10)->create([
            'feed_id' => $feed->id,
            'user_id' => $user->id,
        ]);

        $response = $this->actingAs($user)->get('/rss')->assertOk();
        $props = $response->original->getData()['page']['props'];
        $this->assertCount(10, $props['items']);
        $this->assertNull($props['itemsNextCursor']);
    }

    public function test_pagination_respects_filter_and_feed(): void
    {
        $user = User::factory()->create();
        $a = RssFeed::factory()->for($user)->create(['title' => 'A']);
        $b = RssFeed::factory()->for($user)->create(['title' => 'B']);
        RssItem::factory()->count(60)->create(['feed_id' => $a->id, 'user_id' => $user->id, 'is_read' => false]);
        RssItem::factory()->count(20)->create(['feed_id' => $a->id, 'user_id' => $user->id, 'is_read' => true]);
        RssItem::factory()->count(20)->create(['feed_id' => $b->id, 'user_id' => $user->id, 'is_read' => false]);

        $response = $this->actingAs($user)->get("/rss?feed={$a->id}")->assertOk();
        $props = $response->original->getData()['page']['props'];
        $this->assertCount(50, $props['items']);
        $this->assertNotNull($props['itemsNextCursor']);
        $this->assertEqualsCanonicalizing([$a->id], collect($props['items'])->pluck('feed_id')->unique()->all());
    }
}
