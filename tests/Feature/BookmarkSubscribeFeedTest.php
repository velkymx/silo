<?php

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Models\RssFeed;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BookmarkSubscribeFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscribe_button_adds_the_feed_to_the_reader(): void
    {
        // Fake only the follow-up fetch; AdoptBookmarkFeed itself runs sync in the request.
        Queue::fake([\App\Jobs\Rss\RefreshFeed::class]);
        $user = User::factory()->create();
        $bookmark = Bookmark::factory()->for($user, 'owner')->create([
            'url' => 'https://example.com',
            'feed_url' => 'https://example.com/feed',
        ]);

        $this->actingAs($user)
            ->post("/bookmarks/{$bookmark->id}/subscribe")
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('rss_feeds', [
            'user_id' => $user->id,
            'url' => 'https://example.com/feed',
            'enabled' => true,
        ]);
    }

    public function test_subscribing_twice_does_not_duplicate(): void
    {
        Queue::fake([\App\Jobs\Rss\RefreshFeed::class]);
        $user = User::factory()->create();
        $bookmark = Bookmark::factory()->for($user, 'owner')->create([
            'feed_url' => 'https://example.com/feed',
        ]);

        $this->actingAs($user)->post("/bookmarks/{$bookmark->id}/subscribe");
        $this->actingAs($user)->post("/bookmarks/{$bookmark->id}/subscribe");

        $this->assertSame(1, RssFeed::where('user_id', $user->id)->where('url', 'https://example.com/feed')->count());
    }

    public function test_subscribe_without_feed_url_is_rejected(): void
    {
        $user = User::factory()->create();
        $bookmark = Bookmark::factory()->for($user, 'owner')->create(['feed_url' => null]);

        $this->actingAs($user)
            ->from('/bookmarks')
            ->post("/bookmarks/{$bookmark->id}/subscribe")
            ->assertRedirect('/bookmarks')
            ->assertSessionHasErrors('feed');
    }

    public function test_index_payload_flags_subscribed_feeds(): void
    {
        $user = User::factory()->create();
        Bookmark::factory()->for($user, 'owner')->create(['feed_url' => 'https://a.test/feed', 'title' => 'A']);
        Bookmark::factory()->for($user, 'owner')->create(['feed_url' => 'https://b.test/feed', 'title' => 'B']);
        RssFeed::factory()->for($user)->create(['url' => 'https://a.test/feed']);

        $this->actingAs($user)->get('/bookmarks')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Bookmarks/Index')
                ->where('bookmarks.0.feed_subscribed', true)
                ->where('bookmarks.1.feed_subscribed', false)
            );
    }
}
