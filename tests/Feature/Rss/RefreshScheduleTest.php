<?php

namespace Tests\Feature\Rss;

use App\Jobs\Rss\RefreshAllFeeds;
use App\Jobs\Rss\RefreshFeed;
use App\Models\RssFeed;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RefreshScheduleTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduled_refresh_respects_per_feed_interval(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $neverFetched = RssFeed::factory()->for($user)->create(['last_fetched_at' => null, 'refresh_interval_minutes' => 60]);
        $due = RssFeed::factory()->for($user)->create(['last_fetched_at' => now()->subMinutes(70), 'refresh_interval_minutes' => 60]);
        $notDue = RssFeed::factory()->for($user)->create(['last_fetched_at' => now()->subMinutes(10), 'refresh_interval_minutes' => 60]);
        $muted = RssFeed::factory()->for($user)->create(['last_fetched_at' => null, 'muted_at' => now()]);
        $disabled = RssFeed::factory()->for($user)->disabled()->create(['last_fetched_at' => null]);

        (new RefreshAllFeeds)->handle();

        Queue::assertPushed(RefreshFeed::class, fn (RefreshFeed $j) => $j->feedId === $neverFetched->id);
        Queue::assertPushed(RefreshFeed::class, fn (RefreshFeed $j) => $j->feedId === $due->id);
        Queue::assertNotPushed(RefreshFeed::class, fn (RefreshFeed $j) => $j->feedId === $notDue->id);
        Queue::assertNotPushed(RefreshFeed::class, fn (RefreshFeed $j) => $j->feedId === $muted->id);
        Queue::assertNotPushed(RefreshFeed::class, fn (RefreshFeed $j) => $j->feedId === $disabled->id);
        Queue::assertPushed(RefreshFeed::class, 2);
    }

    public function test_is_due_for_refresh_logic(): void
    {
        $user = User::factory()->create();

        $this->assertTrue(
            RssFeed::factory()->for($user)->make(['last_fetched_at' => null, 'refresh_interval_minutes' => 30])->isDueForRefresh()
        );
        $this->assertTrue(
            RssFeed::factory()->for($user)->make(['last_fetched_at' => now()->subMinutes(31), 'refresh_interval_minutes' => 30])->isDueForRefresh()
        );
        $this->assertFalse(
            RssFeed::factory()->for($user)->make(['last_fetched_at' => now()->subMinutes(29), 'refresh_interval_minutes' => 30])->isDueForRefresh()
        );
        $this->assertFalse(
            RssFeed::factory()->for($user)->make(['last_fetched_at' => null, 'muted_at' => now()])->isDueForRefresh()
        );
    }
}
