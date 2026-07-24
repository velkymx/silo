<?php

namespace Tests\Feature\Rss;

use App\Models\RssFeed;
use App\Models\RssItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecentlyReadFolderTest extends TestCase
{
    use RefreshDatabase;

    public function test_read_filter_shows_only_recently_read_items(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        RssItem::factory()->create([
            'feed_id' => $feed->id,
            'user_id' => $user->id,
            'title' => 'Read yesterday',
            'is_read' => true,
            'read_at' => now()->subDay(),
        ]);
        RssItem::factory()->create([
            'feed_id' => $feed->id,
            'user_id' => $user->id,
            'title' => 'Old read',
            'is_read' => true,
            'read_at' => now()->subDays(30),
        ]);
        RssItem::factory()->create([
            'feed_id' => $feed->id,
            'user_id' => $user->id,
            'title' => 'Unread',
            'is_read' => false,
        ]);

        $response = $this->actingAs($user)->get('/rss?filter=read')->assertOk();
        $titles = collect($response->original->getData()['page']['props']['items'])->pluck('title');
        $this->assertEqualsCanonicalizing(['Read yesterday'], $titles->all());
    }

    public function test_counts_expose_read_recent(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        RssItem::factory()->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'is_read' => true, 'read_at' => now()]);
        RssItem::factory()->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'is_read' => true, 'read_at' => now()->subDays(14)]);

        $response = $this->actingAs($user)->get('/rss')->assertOk();
        $counts = $response->original->getData()['page']['props']['counts'];
        $this->assertSame(1, $counts['read_recent']);
    }
}
