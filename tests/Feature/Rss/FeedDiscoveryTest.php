<?php

namespace Tests\Feature\Rss;

use App\Services\Rss\FeedDiscovery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FeedDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_finds_rss_alternate_link(): void
    {
        Http::fake([
            'example.com/*' => Http::response('<html><head><link rel="alternate" type="application/rss+xml" title="Example Feed" href="https://example.com/feed.xml"/></head><body>hi</body></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $found = (new FeedDiscovery)->discover('https://example.com/');

        $this->assertNotNull($found);
        $this->assertSame('https://example.com/feed.xml', $found->url);
        $this->assertSame('Example Feed', $found->title);
    }

    public function test_prefers_rss_over_atom_when_both_present(): void
    {
        Http::fake([
            'example.com/*' => Http::response('<html><head><link rel="alternate" type="application/atom+xml" href="https://example.com/atom"/><link rel="alternate" type="application/rss+xml" href="https://example.com/rss"/></head></html>', 200),
        ]);

        $found = (new FeedDiscovery)->discover('https://example.com/');

        $this->assertSame('https://example.com/rss', $found->url);
    }

    public function test_falls_back_to_atom_when_no_rss(): void
    {
        Http::fake([
            'example.com/*' => Http::response('<html><head><link rel="alternate" type="application/atom+xml" href="https://example.com/atom"/></head></html>', 200),
        ]);

        $found = (new FeedDiscovery)->discover('https://example.com/');

        $this->assertSame('https://example.com/atom', $found->url);
    }

    public function test_resolves_relative_href_against_page_url(): void
    {
        Http::fake([
            'example.com/*' => Http::response('<html><head><link rel="alternate" type="application/rss+xml" href="/blog/feed"/></head></html>', 200),
        ]);

        $found = (new FeedDiscovery)->discover('https://example.com/blog/');

        $this->assertSame('https://example.com/blog/feed', $found->url);
    }

    public function test_resolves_protocol_relative_href(): void
    {
        Http::fake([
            'example.com/*' => Http::response('<html><head><link rel="alternate" type="application/rss+xml" href="//cdn.example.com/feed"/></head></html>', 200),
        ]);

        $found = (new FeedDiscovery)->discover('https://example.com/');

        $this->assertSame('https://cdn.example.com/feed', $found->url);
    }

    public function test_returns_null_when_no_alternate_link(): void
    {
        Http::fake([
            'example.com/*' => Http::response('<html><head></head><body>nothing</body></html>', 200),
        ]);

        $this->assertNull((new FeedDiscovery)->discover('https://example.com/'));
    }

    public function test_returns_null_on_non_html_response(): void
    {
        Http::fake([
            'example.com/*' => Http::response('{}', 200, ['Content-Type' => 'application/json']),
        ]);

        $this->assertNull((new FeedDiscovery)->discover('https://example.com/'));
    }

    public function test_returns_null_on_http_error(): void
    {
        Http::fake([
            'example.com/*' => Http::response('', 404),
        ]);

        $this->assertNull((new FeedDiscovery)->discover('https://example.com/'));
    }

    public function test_returns_null_on_invalid_url(): void
    {
        $this->assertNull((new FeedDiscovery)->discover('not-a-url'));
        $this->assertNull((new FeedDiscovery)->discover('javascript:alert(1)'));
    }

    public function test_ignores_alternate_links_without_feed_type(): void
    {
        Http::fake([
            'example.com/*' => Http::response('<html><head><link rel="alternate" type="application/manifest+json" href="/manifest"/><link rel="alternate" type="application/rss+xml" href="/rss"/></head></html>', 200),
        ]);

        $found = (new FeedDiscovery)->discover('https://example.com/');
        $this->assertSame('https://example.com/rss', $found->url);
    }

    public function test_controller_endpoint_returns_json_on_success(): void
    {
        Http::fake([
            'example.com/*' => Http::response('<html><head><link rel="alternate" type="application/rss+xml" title="Example" href="https://example.com/feed"/></head></html>', 200, ['Content-Type' => 'text/html']),
        ]);

        $user = \App\Models\User::factory()->create();
        $response = $this->actingAs($user)->postJson('/rss/discover', ['url' => 'https://example.com/']);

        $response->assertOk()->assertJson(['url' => 'https://example.com/feed', 'title' => 'Example', 'source' => 'https://example.com/']);
    }

    public function test_controller_endpoint_returns_422_when_not_found(): void
    {
        Http::fake([
            'example.com/*' => Http::response('<html><head></head></html>', 200),
        ]);

        $user = \App\Models\User::factory()->create();
        $response = $this->actingAs($user)->postJson('/rss/discover', ['url' => 'https://example.com/']);

        $response->assertStatus(422);
    }

    public function test_controller_validates_url(): void
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user)
            ->postJson('/rss/discover', ['url' => 'not-a-url'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('url');
    }
}
