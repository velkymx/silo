<?php

namespace Tests\Feature\Rss;

use App\Models\Notification;
use App\Models\RssFeed;
use App\Models\RssItem;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DispatchSavedSearchesTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_run_is_silent_and_snapshots_count(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        RssItem::factory()->count(5)->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'title' => 'foo']);
        $s = SavedSearch::create(['owner_id' => $user->id, 'name' => 'Foo', 'params' => ['q' => 'foo']]);

        $this->artisan('rss:dispatch-saved-searches')->assertSuccessful();

        $s->refresh();
        $this->assertNotNull($s->last_run_at);
        $this->assertSame(5, $s->last_result_count);
        $this->assertSame(0, Notification::where('user_id', $user->id)->count());
    }

    public function test_increased_count_pushes_notification(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        RssItem::factory()->count(3)->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'title' => 'bar']);
        $s = SavedSearch::create([
            'owner_id' => $user->id,
            'name' => 'Bar',
            'params' => ['q' => 'bar'],
            'last_run_at' => now()->subHour(),
            'last_result_count' => 1,
        ]);

        $this->artisan('rss:dispatch-saved-searches')->assertSuccessful();

        $s->refresh();
        $this->assertSame(3, $s->last_result_count);
        $this->assertSame(1, Notification::where('user_id', $user->id)->count());
    }

    public function test_unchanged_count_does_not_notify(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        RssItem::factory()->count(2)->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'title' => 'baz']);
        $s = SavedSearch::create([
            'owner_id' => $user->id,
            'name' => 'Baz',
            'params' => ['q' => 'baz'],
            'last_run_at' => now()->subHour(),
            'last_result_count' => 2,
        ]);

        $this->artisan('rss:dispatch-saved-searches')->assertSuccessful();

        $this->assertSame(0, Notification::where('user_id', $user->id)->count());
    }

    public function test_decreased_count_does_not_notify(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        RssItem::factory()->count(1)->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'title' => 'qux']);
        $s = SavedSearch::create([
            'owner_id' => $user->id,
            'name' => 'Qux',
            'params' => ['q' => 'qux'],
            'last_run_at' => now()->subHour(),
            'last_result_count' => 5,
        ]);

        $this->artisan('rss:dispatch-saved-searches')->assertSuccessful();

        $s->refresh();
        $this->assertSame(1, $s->last_result_count);
        $this->assertSame(0, Notification::where('user_id', $user->id)->count());
    }

    public function test_notification_links_to_search_url(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        RssItem::factory()->count(3)->create(['feed_id' => $feed->id, 'user_id' => $user->id, 'title' => 'ping']);
        SavedSearch::create([
            'owner_id' => $user->id,
            'name' => 'Ping',
            'params' => ['q' => 'ping'],
            'last_run_at' => now()->subHour(),
            'last_result_count' => 0,
        ]);

        $this->artisan('rss:dispatch-saved-searches')->assertSuccessful();

        $n = Notification::where('user_id', $user->id)->first();
        $this->assertSame('/search?q=ping', $n->url);
        $this->assertStringContainsString('New results for', $n->title);
    }
}
