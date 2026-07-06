<?php

namespace Tests\Feature\Rss;

use App\Automation\EventDispatcher;
use App\Jobs\Rss\RefreshFeed;
use App\Models\RssFeed;
use App\Models\RssItem;
use App\Models\User;
use App\Services\Rss\FaviconFetcher;
use App\Services\Rss\HtmlSanitizer;
use App\Services\Rss\Parser;
use App\Services\Rss\SafeUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ContentSanitizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_sanitizer_strips_scripts_and_handlers_but_keeps_formatting(): void
    {
        $sanitizer = new HtmlSanitizer;

        $dirty = '<p>Hello <strong>world</strong></p>'
            .'<script>alert(1)</script>'
            .'<img src="https://ex.com/a.png" onerror="alert(2)">'
            .'<iframe src="https://evil.example"></iframe>'
            .'<a href="javascript:alert(3)">x</a>';

        $clean = $sanitizer->clean($dirty);

        $this->assertStringContainsString('<strong>world</strong>', $clean);
        $this->assertStringNotContainsStringIgnoringCase('<script', $clean);
        $this->assertStringNotContainsStringIgnoringCase('onerror', $clean);
        $this->assertStringNotContainsStringIgnoringCase('<iframe', $clean);
        $this->assertStringNotContainsStringIgnoringCase('javascript:', $clean);
    }

    public function test_sanitizer_returns_null_for_empty_and_null(): void
    {
        $sanitizer = new HtmlSanitizer;

        $this->assertNull($sanitizer->clean(null));
        $this->assertNull($sanitizer->clean(''));
        $this->assertNull($sanitizer->clean('   '));
    }

    public function test_refresh_stores_sanitized_content(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create([
            'url' => 'https://example.com/feed.xml',
            'site_url' => 'https://example.com',
            'favicon_path' => 'rss-favicons/x.png', // non-empty so the job skips favicon fetch
        ]);

        Http::preventStrayRequests();
        Http::fake([
            'https://example.com/feed.xml' => Http::response($this->maliciousRss(), 200, ['Content-Type' => 'application/rss+xml']),
        ]);

        (new RefreshFeed($feed->id))->handle(
            app(Parser::class),
            app(EventDispatcher::class),
            app(FaviconFetcher::class),
            app(HtmlSanitizer::class),
            app(SafeUrl::class),
        );

        $item = RssItem::where('feed_id', $feed->id)->firstOrFail();
        $this->assertNotNull($item->content);
        $this->assertStringContainsString('Legit body', $item->content);
        $this->assertStringNotContainsStringIgnoringCase('<script', $item->content);
        $this->assertStringNotContainsStringIgnoringCase('onerror', $item->content);
    }

    private function maliciousRss(): string
    {
        return <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/">
  <channel>
    <title>Evil Feed</title>
    <link>https://example.com</link>
    <item>
      <title>Post</title>
      <link>https://example.com/post</link>
      <guid>evil-1</guid>
      <pubDate>Mon, 06 Jul 2026 12:00:00 +0000</pubDate>
      <content:encoded><![CDATA[<p>Legit body</p><script>alert(1)</script><img src="https://x/y.png" onerror="alert(2)">]]></content:encoded>
    </item>
  </channel>
</rss>
XML;
    }
}
