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

class SsrfGuardTest extends TestCase
{
    use RefreshDatabase;

    public function test_refresh_blocks_private_address_feed_without_fetching(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create([
            'url' => 'http://169.254.169.254/latest/meta-data/',
        ]);

        Http::preventStrayRequests();
        Http::fake(); // any outbound call would throw as a stray request

        (new RefreshFeed($feed->id))->handle(
            app(Parser::class),
            app(EventDispatcher::class),
            app(FaviconFetcher::class),
            app(HtmlSanitizer::class),
            app(SafeUrl::class),
        );

        $feed->refresh();
        $this->assertSame(0, RssItem::where('feed_id', $feed->id)->count());
        $this->assertSame(1, $feed->consecutive_failures);
        $this->assertStringContainsString('private or reserved', (string) $feed->last_error);
        Http::assertNothingSent();
    }

    public function test_add_feed_rejects_non_http_scheme(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/rss/feeds', [
                'title' => 'Bad',
                'url' => 'file:///etc/passwd',
            ])
            ->assertSessionHasErrors('url');
    }

    public function test_import_from_url_rejects_private_address(): void
    {
        $user = User::factory()->create();

        Http::preventStrayRequests();
        Http::fake();

        $this->actingAs($user)
            ->post('/rss/opml/import-url', ['url' => 'http://127.0.0.1/subs.opml'])
            ->assertRedirect()
            ->assertSessionHas('error');

        Http::assertNothingSent();
    }
}
