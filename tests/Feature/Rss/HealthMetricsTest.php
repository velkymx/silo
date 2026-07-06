<?php

namespace Tests\Feature\Rss;

use App\Jobs\Rss\RefreshFeed;
use App\Models\RssFeed;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HealthMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_successful_fetch_records_status_and_response_time(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create(['url' => 'https://example.com/feed.xml']);

        Http::fake([
            'https://example.com/feed.xml' => Http::response($this->rssFixture(), 200, ['Content-Type' => 'application/rss+xml']),
        ]);

        (new RefreshFeed($feed->id))->handle(
            app(\App\Services\Rss\Parser::class),
            app(\App\Automation\EventDispatcher::class),
            app(\App\Services\Rss\FaviconFetcher::class),
        );

        $feed->refresh();
        $this->assertSame(200, $feed->last_http_status);
        $this->assertNotNull($feed->last_response_time_ms);
        $this->assertSame(0, $feed->consecutive_failures);
    }

    public function test_404_records_status_and_increments_failures(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create(['url' => 'https://example.com/feed.xml']);

        Http::fake([
            'https://example.com/feed.xml' => Http::response('', 404),
        ]);

        (new RefreshFeed($feed->id))->handle(
            app(\App\Services\Rss\Parser::class),
            app(\App\Automation\EventDispatcher::class),
            app(\App\Services\Rss\FaviconFetcher::class),
        );

        $feed->refresh();
        $this->assertSame(404, $feed->last_http_status);
        $this->assertSame(1, $feed->consecutive_failures);
    }

    public function test_successful_fetch_after_failure_resets_counter(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create([
            'url' => 'https://example.com/feed.xml',
            'consecutive_failures' => 5,
        ]);

        Http::fake([
            'https://example.com/feed.xml' => Http::response($this->rssFixture(), 200, ['Content-Type' => 'application/rss+xml']),
        ]);

        (new RefreshFeed($feed->id))->handle(
            app(\App\Services\Rss\Parser::class),
            app(\App\Automation\EventDispatcher::class),
            app(\App\Services\Rss\FaviconFetcher::class),
        );

        $feed->refresh();
        $this->assertSame(0, $feed->consecutive_failures);
    }

    public function test_304_resets_failures_and_records_status(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create([
            'url' => 'https://example.com/feed.xml',
            'consecutive_failures' => 3,
        ]);

        Http::fake([
            'https://example.com/feed.xml' => Http::response('', 304),
        ]);

        (new RefreshFeed($feed->id))->handle(
            app(\App\Services\Rss\Parser::class),
            app(\App\Automation\EventDispatcher::class),
            app(\App\Services\Rss\FaviconFetcher::class),
        );

        $feed->refresh();
        $this->assertSame(304, $feed->last_http_status);
        $this->assertSame(0, $feed->consecutive_failures);
    }

    public function test_connection_error_increments_failures_without_status(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create(['url' => 'https://unreachable.invalid/feed.xml']);

        Http::fake(['*' => Http::failedConnection()]);

        (new RefreshFeed($feed->id))->handle(
            app(\App\Services\Rss\Parser::class),
            app(\App\Automation\EventDispatcher::class),
            app(\App\Services\Rss\FaviconFetcher::class),
        );

        $feed->refresh();
        $this->assertNull($feed->last_http_status);
        $this->assertSame(1, $feed->consecutive_failures);
        $this->assertStringContainsString('connection', (string) $feed->last_error);
    }

    public function test_inbox_exposes_health_metrics_on_each_feed(): void
    {
        $user = User::factory()->create();
        RssFeed::factory()->for($user)->create([
            'last_http_status' => 200,
            'last_response_time_ms' => 450,
            'consecutive_failures' => 0,
        ]);

        $response = $this->actingAs($user)->get('/rss')->assertOk();
        $feeds = $response->original->getData()['page']['props']['feeds'];
        $this->assertSame(200, $feeds[0]['last_http_status']);
        $this->assertSame(450, $feeds[0]['last_response_time_ms']);
        $this->assertSame(0, $feeds[0]['consecutive_failures']);
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
      <title>Hi</title>
      <link>https://example.com/hi</link>
      <guid>hi-1</guid>
      <pubDate>Mon, 06 Jul 2026 12:00:00 +0000</pubDate>
    </item>
  </channel>
</rss>
XML;
    }
}
