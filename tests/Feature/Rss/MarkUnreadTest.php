<?php

namespace Tests\Feature\Rss;

use App\Models\RssFeed;
use App\Models\RssItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarkUnreadTest extends TestCase
{
    use RefreshDatabase;

    public function test_mark_unread_clears_read_flag_and_timestamp(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        $item = RssItem::factory()->create([
            'feed_id' => $feed->id,
            'user_id' => $user->id,
            'is_read' => true,
            'read_at' => now(),
        ]);

        $this->actingAs($user)
            ->post("/rss/items/{$item->id}/unread")
            ->assertRedirect();

        $item->refresh();
        $this->assertFalse($item->is_read);
        $this->assertNull($item->read_at);
    }

    public function test_mark_unread_is_idempotent(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        $item = RssItem::factory()->create([
            'feed_id' => $feed->id,
            'user_id' => $user->id,
            'is_read' => false,
        ]);

        $this->actingAs($user)
            ->post("/rss/items/{$item->id}/unread")
            ->assertRedirect();

        $this->assertFalse($item->fresh()->is_read);
    }

    public function test_mark_unread_json_response(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        $item = RssItem::factory()->create([
            'feed_id' => $feed->id,
            'user_id' => $user->id,
            'is_read' => true,
            'read_at' => now(),
        ]);

        $this->actingAs($user)
            ->postJson("/rss/items/{$item->id}/unread")
            ->assertOk()
            ->assertJson(['ok' => true, 'is_read' => false]);
    }

    public function test_other_user_cannot_mark_unread(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $feed = RssFeed::factory()->for($owner)->create();
        $item = RssItem::factory()->create([
            'feed_id' => $feed->id,
            'user_id' => $owner->id,
            'is_read' => true,
            'read_at' => now(),
        ]);

        $this->actingAs($other)
            ->post("/rss/items/{$item->id}/unread")
            ->assertForbidden();

        $this->assertTrue($item->fresh()->is_read);
    }
}
