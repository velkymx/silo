<?php

namespace Tests\Feature\Rss;

use App\Jobs\Rss\RefreshFeed;
use App\Models\RssFeed;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FaviconTest extends TestCase
{
    use RefreshDatabase;

    public function test_fetcher_discovers_link_rel_icon(): void
    {
        Http::fake([
            'example.com/*' => Http::response('<html><head><link rel="icon" href="https://example.com/favicon.png"></head></html>', 200, ['Content-Type' => 'text/html']),
            'example.com/favicon.png' => Http::response($this->pngBytes(), 200, ['Content-Type' => 'image/png']),
        ]);

        $fetcher = new \App\Services\Rss\FaviconFetcher;
        $path = $fetcher->fetch('https://example.com/blog');

        $this->assertNotNull($path);
        $this->assertStringEndsWith('.png', $path);
        $this->assertTrue(Storage::disk('local')->exists($path));
    }

    public function test_fetcher_falls_back_to_root_favicon_ico(): void
    {
        Http::fake([
            'example.com/' => Http::response('<html><body>no icon here</body></html>', 200, ['Content-Type' => 'text/html']),
            'example.com/favicon.ico' => Http::response("\x00\x00\x01\x00\x02\x00", 200, ['Content-Type' => 'image/x-icon']),
        ]);

        $path = (new \App\Services\Rss\FaviconFetcher)->fetch('https://example.com');

        $this->assertNotNull($path);
        $this->assertStringEndsWith('.ico', $path);
    }

    public function test_fetcher_returns_null_on_non_http(): void
    {
        $this->assertNull((new \App\Services\Rss\FaviconFetcher)->fetch('not-a-url'));
        $this->assertNull((new \App\Services\Rss\FaviconFetcher)->fetch('javascript:alert(1)'));
    }

    public function test_fetcher_returns_null_on_http_failure(): void
    {
        Http::fake(['example.com/*' => Http::response('', 404)]);
        $this->assertNull((new \App\Services\Rss\FaviconFetcher)->fetch('https://example.com'));
    }

    public function test_fetcher_returns_null_on_oversized_response(): void
    {
        Http::fake(['example.com/*' => Http::response(str_repeat('a', 300 * 1024), 200)]);
        $this->assertNull((new \App\Services\Rss\FaviconFetcher)->fetch('https://example.com'));
    }

    public function test_refresh_job_persists_favicon_path(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create([
            'url' => 'https://example.com/feed.xml',
            'site_url' => 'https://example.com',
        ]);

        $html = '<html><head><link rel="icon" href="/favicon.png"></head></html>';
        $png = $this->pngBytes();
        Http::preventStrayRequests();
        Http::fake([
            'https://example.com/feed.xml' => Http::response($this->rssFixture(), 200, ['Content-Type' => 'application/rss+xml']),
            '*' => Http::response($html, 200, ['Content-Type' => 'text/html']),
        ]);

        (new RefreshFeed($feed->id))->handle(
            app(\App\Services\Rss\Parser::class),
            app(\App\Automation\EventDispatcher::class),
            app(\App\Services\Rss\FaviconFetcher::class),
        );

        $feed->refresh();
        $this->assertNotNull($feed->favicon_path, 'Feed did not get a favicon_path after refresh. site_url='.$feed->site_url);
        $this->assertStringEndsWith('.png', $feed->favicon_path);
    }

    public function test_favicon_endpoint_serves_cached_icon(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create(['favicon_path' => 'rss-favicons/test.png']);
        Storage::disk('local')->put('rss-favicons/test.png', $this->pngBytes());

        $this->actingAs($user)
            ->get("/rss/feeds/{$feed->id}/favicon")
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
    }

    public function test_favicon_endpoint_404s_when_no_favicon(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create(['favicon_path' => null]);

        $this->actingAs($user)
            ->get("/rss/feeds/{$feed->id}/favicon")
            ->assertNotFound();
    }

    public function test_favicon_endpoint_is_policy_gated(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $feed = RssFeed::factory()->for($owner)->create(['favicon_path' => 'rss-favicons/test.png']);
        Storage::disk('local')->put('rss-favicons/test.png', $this->pngBytes());

        $this->actingAs($other)
            ->get("/rss/feeds/{$feed->id}/favicon")
            ->assertForbidden();
    }

    private function pngBytes(): string
    {
        // 1x1 PNG (8 bytes signature + IHDR + IDAT + IEND chunks).
        return "\x89PNG\r\n\x1a\n".
            "\x00\x00\x00\x0dIHDR\x00\x00\x00\x01\x00\x00\x00\x01".
            "\x08\x06\x00\x00\x00\x1f\x15\xc4\x89".
            "\x00\x00\x00\x0dIDATx\x9cc\xf8\xcf\xc0\x00\x00\x00\x03\x00\x01".
            "\x05\xfe\xd3\x00\x00\x00\x00IEND\xaeB\x60\x82";
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
