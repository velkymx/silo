<?php

namespace Tests\Feature\Rss;

use App\Automation\Events\AutomationEvent;
use App\Automation\Subscribers\RssDefaultSubscriber;
use App\Models\Notification;
use App\Models\RssFeed;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedNotificationTest extends TestCase
{
    use RefreshDatabase;

    private function fetched(RssFeed $feed, int $newCount): void
    {
        app(RssDefaultSubscriber::class)->handle(AutomationEvent::make(
            'rss.feed.fetched',
            $feed->user_id,
            ['feed_id' => $feed->id, 'new_count' => $newCount, 'not_modified' => false],
        ));
    }

    public function test_a_fetch_with_new_items_creates_one_aggregated_notification(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->create(['user_id' => $user->id, 'title' => 'Laravel News']);

        $this->fetched($feed, 123);

        $this->assertSame(1, Notification::count());
        $n = Notification::first();
        $this->assertSame('Laravel News has 123 new articles', $n->title);
        $this->assertSame('rss.feed.new_items', $n->type);
        $this->assertSame($user->id, $n->user_id);
        $this->assertStringContainsString("feed={$feed->id}", $n->url);
    }

    public function test_repeated_fetches_coalesce_into_the_unread_notification(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->create(['user_id' => $user->id, 'title' => 'Laravel News']);

        $this->fetched($feed, 3);
        $this->fetched($feed, 4);

        $this->assertSame(1, Notification::count());
        $this->assertSame('Laravel News has 7 new articles', Notification::first()->title);
    }

    public function test_a_read_notification_is_not_reused(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->create(['user_id' => $user->id, 'title' => 'Laravel News']);

        $this->fetched($feed, 3);
        Notification::first()->update(['read_at' => now()]);
        $this->fetched($feed, 2);

        $this->assertSame(2, Notification::count());
        $this->assertSame('Laravel News has 2 new articles', Notification::whereNull('read_at')->first()->title);
    }

    public function test_no_notification_for_zero_new_items_or_muted_feeds(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->create(['user_id' => $user->id]);
        $muted = RssFeed::factory()->create(['user_id' => $user->id, 'muted_at' => now()]);

        $this->fetched($feed, 0);
        $this->fetched($muted, 9);

        $this->assertSame(0, Notification::count());
    }

    public function test_uses_the_singular_for_one_article(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->create(['user_id' => $user->id, 'title' => 'Planet PostgreSQL']);

        $this->fetched($feed, 1);

        $this->assertSame('Planet PostgreSQL has 1 new article', Notification::first()->title);
    }
}
