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
use App\Services\Http\SafeUrl;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IngestNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_oversize_title_and_missing_url_do_not_drop_the_item(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create([
            'url' => 'https://example.com/feed.xml',
            'site_url' => null,
            'favicon_path' => 'rss-favicons/x.png', // non-empty so the job skips favicon fetch
        ]);

        $longTitle = str_repeat('A', 300);
        $longAuthor = str_repeat('B', 300);
        $rss = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Feed</title>
    <item>
      <title>{$longTitle}</title>
      <guid>only-guid-no-link</guid>
      <author>{$longAuthor}</author>
      <pubDate>Mon, 06 Jul 2026 12:00:00 +0000</pubDate>
    </item>
  </channel>
</rss>
XML;

        Http::preventStrayRequests();
        Http::fake([
            'https://example.com/feed.xml' => Http::response($rss, 200, ['Content-Type' => 'application/rss+xml']),
        ]);

        (new RefreshFeed($feed->id))->handle(
            app(Parser::class),
            app(EventDispatcher::class),
            app(FaviconFetcher::class),
            app(HtmlSanitizer::class),
            app(SafeUrl::class),
        );

        $item = RssItem::where('feed_id', $feed->id)->first();
        $this->assertNotNull($item, 'Item must be stored, not silently skipped');
        $this->assertSame(255, mb_strlen($item->title));
        $this->assertSame(255, mb_strlen((string) $item->author));
        $this->assertSame('', $item->url, 'Missing entry+site url falls back to empty string, not NULL');
    }

    public function test_refetching_the_same_feed_does_not_duplicate_items(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create([
            'url' => 'https://example.com/feed.xml',
            'favicon_path' => 'rss-favicons/x.png',
        ]);

        $rss = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"><channel>
  <title>Feed</title><link>https://example.com</link>
  <item><title>One</title><guid>g-1</guid><link>https://example.com/1</link></item>
  <item><title>Two</title><guid>g-2</guid><link>https://example.com/2</link></item>
</channel></rss>
XML;

        Http::preventStrayRequests();
        Http::fake(['https://example.com/feed.xml' => Http::response($rss, 200, ['Content-Type' => 'application/rss+xml'])]);

        $run = fn () => (new RefreshFeed($feed->id))->handle(
            app(Parser::class),
            app(EventDispatcher::class),
            app(FaviconFetcher::class),
            app(HtmlSanitizer::class),
            app(SafeUrl::class),
        );

        $run();
        $run();

        $this->assertSame(2, RssItem::where('feed_id', $feed->id)->count());
    }
}
