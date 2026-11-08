<?php

namespace Tests\Feature\Http;

use App\Services\Http\SafeUrl;
use Composer\InstalledVersions;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Guzzle 8 compatibility guard. The Guzzle 7 -> 8 major bump changed the HTTP
 * layer under Laravel's Http facade. Every outbound fetch in the app
 * (RSS refresh, feed discovery, OPML import, favicons, health check) passes
 * SafeUrl::allowRedirects() straight into Guzzle via withOptions().
 *
 * Http::fake() swaps only the transport handler — the Guzzle middleware stack
 * still parses the redirect options — so this proves Guzzle 8 accepts the
 * exact option array the app uses, without a flaky external/local server.
 */
class GuzzleTransportTest extends TestCase
{
    public function test_guzzle_8_accepts_the_apps_redirect_options(): void
    {
        $this->assertMatchesRegularExpression(
            '/^v?8\./',
            (string) InstalledVersions::getPrettyVersion('guzzlehttp/guzzle'),
        );

        Http::fake([
            'example.com/*' => Http::response(['ok' => true], 200, ['Content-Type' => 'application/json']),
        ]);

        $response = Http::timeout(5)
            ->withOptions(['allow_redirects' => (new SafeUrl)->allowRedirects(5)])
            ->get('https://example.com/feed.xml');

        $this->assertTrue($response->successful());
        $this->assertTrue($response->json('ok'));
        Http::assertSent(fn ($request) => $request->url() === 'https://example.com/feed.xml');
    }
}
