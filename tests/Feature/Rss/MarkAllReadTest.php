<?php

namespace Tests\Feature\Rss;

use App\Models\RssFeed;
use App\Models\RssItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarkAllReadTest extends TestCase
{
    use RefreshDatabase;

    public function test_marks_all_unmuted_unread_items_read(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        $muted = RssFeed::factory()->for($user)->create(['muted_at' => now()]);

        $unread = RssItem::factory()->count(3)->for($feed, 'feed')->create(['user_id' => $user->id, 'is_read' => false]);
        $mutedItem = RssItem::factory()->for($muted, 'feed')->create(['user_id' => $user->id, 'is_read' => false]);

        $this->actingAs($user)
            ->post('/rss/items/mark-all-read')
            ->assertRedirect()
            ->assertSessionHas('success');

        foreach ($unread as $item) {
            $this->assertTrue($item->fresh()->is_read);
            $this->assertNotNull($item->fresh()->read_at);
        }
        $this->assertFalse($mutedItem->fresh()->is_read, 'Muted feed items must not be marked read');
    }

    public function test_respects_active_smart_folder_filter(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();

        $todayItem = RssItem::factory()->for($feed, 'feed')->create(['user_id' => $user->id, 'is_read' => false, 'published_at' => now()]);
        $oldItem = RssItem::factory()->for($feed, 'feed')->create(['user_id' => $user->id, 'is_read' => false, 'published_at' => now()->subDays(10)]);

        $this->actingAs($user)
            ->post('/rss/items/mark-all-read', ['filter' => 'today'])
            ->assertRedirect();

        $this->assertTrue($todayItem->fresh()->is_read);
        $this->assertFalse($oldItem->fresh()->is_read, 'Only the active filter set should be marked');
    }

    public function test_respects_selected_feed(): void
    {
        $user = User::factory()->create();
        $feedA = RssFeed::factory()->for($user)->create();
        $feedB = RssFeed::factory()->for($user)->create();

        $a = RssItem::factory()->for($feedA, 'feed')->create(['user_id' => $user->id, 'is_read' => false]);
        $b = RssItem::factory()->for($feedB, 'feed')->create(['user_id' => $user->id, 'is_read' => false]);

        $this->actingAs($user)
            ->post('/rss/items/mark-all-read', ['feed' => $feedA->id])
            ->assertRedirect();

        $this->assertTrue($a->fresh()->is_read);
        $this->assertFalse($b->fresh()->is_read);
    }

    public function test_does_not_touch_other_users_items(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $otherFeed = RssFeed::factory()->for($other)->create();
        $otherItem = RssItem::factory()->for($otherFeed, 'feed')->create(['user_id' => $other->id, 'is_read' => false]);

        $this->actingAs($user)->post('/rss/items/mark-all-read')->assertRedirect();

        $this->assertFalse($otherItem->fresh()->is_read);
    }
}
