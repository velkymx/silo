<?php

namespace Tests\Feature\Rss;

use App\Jobs\Rss\RefreshAllFeeds;
use App\Jobs\Rss\RefreshFeed;
use App\Models\RssFeed;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class FeedMuteTest extends TestCase
{
    use RefreshDatabase;

    public function test_mute_sets_muted_at_and_hides_feed_from_inbox_sidebar(): void
    {
        $user = User::factory()->create();
        $a = RssFeed::factory()->for($user)->create(['title' => 'A', 'folder' => 'Tech']);
        $b = RssFeed::factory()->for($user)->create(['title' => 'B', 'folder' => 'Tech']);

        $this->actingAs($user)
            ->post("/rss/feeds/{$a->id}/mute")
            ->assertRedirect();

        $a->refresh();
        $this->assertTrue($a->isMuted());
        $this->assertNotNull($a->muted_at);
        $b->refresh();
        $this->assertFalse($b->isMuted());

        // Inbox payload should exclude muted by default.
        $this->actingAs($user)->get('/rss')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Rss/Index')
                ->where('feeds', fn ($feeds) => collect($feeds)->pluck('id')->all() === [$b->id])
                ->where('counts.muted', 1)
            );
    }

    public function test_show_muted_query_includes_muted_feeds_in_sidebar(): void
    {
        $user = User::factory()->create();
        $a = RssFeed::factory()->for($user)->create(['title' => 'A']);
        $a->mute();

        $this->actingAs($user)->get('/rss?show_muted=1')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Rss/Index')
                ->where('feeds.0.id', $a->id)
                ->where('feeds.0.muted', true)
                ->where('filters.show_muted', true)
            );
    }

    public function test_muted_feeds_are_excluded_from_items_query(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        $item = \App\Models\RssItem::factory()->create([
            'feed_id' => $feed->id,
            'user_id' => $user->id,
        ]);
        $feed->mute();

        $this->actingAs($user)->get('/rss')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Rss/Index')
                ->where('items', fn ($items) => collect($items)->pluck('id')->doesntContain($item->id))
            );
    }

    public function test_unmute_clears_muted_at_and_restores_visibility(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        $feed->mute();

        $this->actingAs($user)
            ->post("/rss/feeds/{$feed->id}/unmute")
            ->assertRedirect();

        $feed->refresh();
        $this->assertFalse($feed->isMuted());
        $this->assertNull($feed->muted_at);
    }

    public function test_mute_is_idempotent(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();

        $this->actingAs($user)->post("/rss/feeds/{$feed->id}/mute");
        $first = $feed->fresh()->muted_at;
        sleep(1);
        $this->actingAs($user)->post("/rss/feeds/{$feed->id}/mute");
        $this->assertSame($first->toIso8601String(), $feed->fresh()->muted_at->toIso8601String());
    }

    public function test_refresh_all_skips_muted_feeds(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $a = RssFeed::factory()->for($user)->create(['enabled' => true]);
        $b = RssFeed::factory()->for($user)->create(['enabled' => true]);
        $a->mute();

        $this->actingAs($user)->post('/rss/feeds/refresh-all')
            ->assertRedirect()
            ->assertSessionHas('success', 'Queued 1 feed(s) for refresh.');

        Queue::assertPushed(RefreshFeed::class, 1);
        Queue::assertPushed(RefreshFeed::class, fn ($job) => $job->feedId === $b->id);
    }

    public function test_scheduled_refresh_all_skips_muted_feeds(): void
    {
        Queue::fake();
        $a = RssFeed::factory()->create(['enabled' => true]);
        $b = RssFeed::factory()->create(['enabled' => true]);
        $a->mute();

        (new RefreshAllFeeds)->handle();

        Queue::assertPushed(RefreshFeed::class, 1);
        Queue::assertPushed(RefreshFeed::class, fn ($job) => $job->feedId === $b->id);
    }

    public function test_direct_refresh_of_muted_feed_is_rejected(): void
    {
        Queue::fake();
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        $feed->mute();

        $this->actingAs($user)
            ->post("/rss/feeds/{$feed->id}/refresh")
            ->assertRedirect()
            ->assertSessionHas('error');

        Queue::assertNothingPushed();
    }

    public function test_refresh_job_itself_short_circuits_when_muted_at_runtime(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create(['enabled' => true]);
        \Illuminate\Support\Facades\Http::fake();
        $feed->mute();

        (new RefreshFeed($feed->id))->handle(
            app(\App\Services\Rss\Parser::class),
            app(\App\Automation\EventDispatcher::class),
        );

        \Illuminate\Support\Facades\Http::assertNothingSent();
        $feed->refresh();
        $this->assertNull($feed->last_fetched_at);
    }

    public function test_other_user_cannot_mute_my_feed(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $feed = RssFeed::factory()->for($owner)->create();

        $this->actingAs($other)
            ->post("/rss/feeds/{$feed->id}/mute")
            ->assertForbidden();

        $this->assertFalse($feed->fresh()->isMuted());
    }
}
