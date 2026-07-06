<?php

namespace Tests\Feature\Rss;

use App\Jobs\Rss\RefreshFeed;
use App\Models\AuditLog;
use App\Models\RssFeed;
use App\Models\RssItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ActivityLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_logs_feed_create(): void
    {
        Http::fake(); // the create dispatches RefreshFeed (sync) — keep it off the network
        $user = User::factory()->create();
        $this->actingAs($user)
            ->post('/rss/feeds', [
                'title' => 'Laravel News',
                'url' => 'https://laravel-news.com/feed.xml',
                'folder' => 'Tech',
                'enabled' => true,
            ])
            ->assertRedirect();

        $log = AuditLog::where('action', 'rss.feed.create')->first();
        $this->assertNotNull($log);
        $this->assertSame('Laravel News', $log->file_name);
        $this->assertSame($user->id, $log->user_id);
        $this->assertNull($log->file_id);
    }

    public function test_destroy_logs_feed_delete(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create(['title' => 'A']);
        $this->actingAs($user)->delete("/rss/feeds/{$feed->id}")->assertRedirect();

        $log = AuditLog::where('action', 'rss.feed.delete')->first();
        $this->assertNotNull($log);
        $this->assertSame('A', $log->file_name);
    }

    public function test_mute_unmute_log(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();

        $this->actingAs($user)->post("/rss/feeds/{$feed->id}/mute")->assertRedirect();
        $this->assertSame(1, AuditLog::where('action', 'rss.feed.mute')->count());

        $this->actingAs($user)->post("/rss/feeds/{$feed->id}/unmute")->assertRedirect();
        $this->assertSame(1, AuditLog::where('action', 'rss.feed.unmute')->count());
    }

    public function test_refresh_logs_action(): void
    {
        Http::fake();
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        $this->actingAs($user)->post("/rss/feeds/{$feed->id}/refresh")->assertRedirect();

        $this->assertSame(1, AuditLog::where('action', 'rss.feed.refresh')->count());
    }

    public function test_toggle_star_logs_action(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        $item = RssItem::factory()->create(['feed_id' => $feed->id, 'user_id' => $user->id]);

        $this->actingAs($user)->post("/rss/items/{$item->id}/star")->assertRedirect();
        $this->assertSame(1, AuditLog::where('action', 'rss.item.star')->count());

        $this->actingAs($user)->post("/rss/items/{$item->id}/star")->assertRedirect();
        $this->assertSame(1, AuditLog::where('action', 'rss.item.unstar')->count());
    }

    public function test_refresh_job_logs_new_article_discovered(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create(['url' => 'https://example.com/feed.xml']);

        Http::fake([
            'example.com/*' => Http::response($this->rssFixture(), 200, ['Content-Type' => 'application/rss+xml']),
        ]);

        (new RefreshFeed($feed->id))->handle(
            app(\App\Services\Rss\Parser::class),
            app(\App\Automation\EventDispatcher::class),
            app(\App\Services\Rss\FaviconFetcher::class),
            app(\App\Services\Rss\HtmlSanitizer::class),
            app(\App\Services\Rss\SafeUrl::class),
        );

        $this->assertSame(1, AuditLog::where('action', 'rss.item.create')->count());
        $log = AuditLog::where('action', 'rss.item.create')->first();
        $this->assertSame('Laravel 12 released', $log->file_name);
    }

    public function test_refresh_all_logs_action(): void
    {
        Http::fake();
        $user = User::factory()->create();
        RssFeed::factory()->count(3)->for($user)->create();
        $this->actingAs($user)->post('/rss/feeds/refresh-all')->assertRedirect();

        $this->assertSame(1, AuditLog::where('action', 'rss.feed.refresh_all')->count());
        $this->assertSame(3, AuditLog::where('action', 'rss.feed.refresh_all')->first()->meta['count']);
    }

    private function rssFixture(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Test</title>
    <link>https://example.com</link>
    <item>
      <title>Laravel 12 released</title>
      <link>https://example.com/laravel-12</link>
      <guid>https://example.com/laravel-12</guid>
      <pubDate>Mon, 06 Jul 2026 12:00:00 +0000</pubDate>
    </item>
  </channel>
</rss>
XML;
    }
}
