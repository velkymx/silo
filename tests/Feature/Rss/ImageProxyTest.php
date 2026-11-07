<?php

namespace Tests\Feature\Rss;

use App\Http\Resources\Rss\Item as RssItemResource;
use App\Models\RssFeed;
use App\Models\RssItem;
use App\Models\User;
use App\Services\Rss\ImageProxy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class ImageProxyTest extends TestCase
{
    use RefreshDatabase;

    private function png(): string
    {
        return base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='
        );
    }

    public function test_proxy_serves_a_remote_image_from_our_origin_and_caches_it(): void
    {
        Storage::fake('local');
        Http::fake(['example.com/*' => Http::response($this->png(), 200, ['Content-Type' => 'image/png'])]);
        $user = User::factory()->create();

        $url = app(ImageProxy::class)->url('https://example.com/a.png');
        $this->assertNotNull($url);
        $this->assertStringContainsString('/rss/image?', $url);

        $response = $this->actingAs($user)->get($url);
        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString("default-src 'none'", $response->headers->get('Content-Security-Policy'));
        $this->assertSame($this->png(), $response->getContent());

        // Second request is served from the local cache, not re-fetched.
        Http::assertSentCount(1);
        $this->actingAs($user)->get($url)->assertOk();
        Http::assertSentCount(1);
        $this->assertCount(1, Storage::disk('local')->files('rss-images'));
    }

    public function test_unsigned_and_internal_urls_are_rejected(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        // Forging a proxy URL without the app signature is not allowed.
        $this->actingAs($user)
            ->get('/rss/image?url='.urlencode('https://example.com/a.png'))
            ->assertForbidden();

        // A correctly signed URL pointing at loopback still fails SafeUrl.
        Http::fake();
        $loopback = URL::signedRoute('rss.image', ['url' => 'http://127.0.0.1/a.png']);
        $this->actingAs($user)->get($loopback)->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_svg_and_non_image_bodies_are_refused(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        foreach (['<svg onload="alert(1)"/>', '<html><body>not an image</body></html>'] as $i => $body) {
            Http::fake(['example.com/*' => Http::response($body, 200, ['Content-Type' => 'image/png'])]);
            $url = app(ImageProxy::class)->url("https://example.com/bad-{$i}.png");
            $this->actingAs($user)->get($url)->assertNotFound();
        }

        $this->assertCount(0, Storage::disk('local')->files('rss-images'));
    }

    public function test_oversize_images_are_refused(): void
    {
        Storage::fake('local');
        Http::fake(['example.com/*' => Http::response(str_repeat("\x89PNG\r\n\x1a\n", 400000), 200)]);
        $user = User::factory()->create();

        $url = app(ImageProxy::class)->url('https://example.com/huge.png');
        $this->actingAs($user)->get($url)->assertNotFound();
        $this->assertCount(0, Storage::disk('local')->files('rss-images'));
    }

    public function test_resource_exposes_signed_urls_for_thumbnail_and_inline_images(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        $item = RssItem::factory()->create([
            'feed_id' => $feed->id,
            'user_id' => $user->id,
            'image_url' => 'https://example.com/featured.png',
            'content' => '<p>Hello</p><img src="https://example.com/inline.png" alt="x">',
        ]);

        $wire = (new RssItemResource($item))->withFull()->toArray(request());

        $this->assertStringContainsString('/rss/image?', $wire['image_url']);
        $this->assertStringNotContainsString('example.com/featured.png', $wire['image_url']);
        $this->assertStringContainsString('/rss/image?', $wire['content']);
        $this->assertStringNotContainsString('src="https://example.com/inline.png"', $wire['content']);
        $this->assertStringContainsString('<p>Hello</p>', $wire['content']);
    }

    public function test_url_returns_null_for_empty_and_non_http_sources(): void
    {
        $proxy = app(ImageProxy::class);

        $this->assertNull($proxy->url(null));
        $this->assertNull($proxy->url(''));
        $this->assertNull($proxy->url('javascript:alert(1)'));
        $this->assertNull($proxy->url('data:image/png;base64,AAAA'));
        $this->assertNull($proxy->url('not a url'));
    }
}
