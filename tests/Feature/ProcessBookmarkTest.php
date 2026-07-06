<?php

namespace Tests\Feature;

use App\Jobs\ProcessBookmark;
use App\Models\Bookmark;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessBookmarkTest extends TestCase
{
    use RefreshDatabase;

    public function test_marks_dead_when_the_url_does_not_respond_ok(): void
    {
        Http::fake(['*' => Http::response('', 404)]);
        $bookmark = Bookmark::factory()->create(['url' => 'https://gone.example.com', 'status' => 'pending']);

        (new ProcessBookmark($bookmark->id))->handle();

        $this->assertSame('dead', $bookmark->fresh()->status);
        $this->assertNotNull($bookmark->fresh()->last_checked_at);
    }

    public function test_marks_alive_and_stores_the_favicon(): void
    {
        Storage::fake('public');
        Http::fake([
            'https://ok.example.com' => Http::response('<html><head><link rel="icon" href="/fav.png"></head></html>', 200),
            'https://ok.example.com/fav.png' => Http::response('PNGBYTES', 200, ['Content-Type' => 'image/png']),
        ]);
        $bookmark = Bookmark::factory()->create(['url' => 'https://ok.example.com', 'status' => 'pending']);

        (new ProcessBookmark($bookmark->id))->handle();

        $bookmark->refresh();
        $this->assertSame('alive', $bookmark->status);
        $this->assertNotNull($bookmark->icon_path);
        Storage::disk('public')->assertExists($bookmark->icon_path);
    }

    public function test_detects_an_rss_feed(): void
    {
        Storage::fake('public');
        Http::fake([
            'https://blog.example.com' => Http::response(
                '<html><head><link rel="alternate" type="application/rss+xml" href="/feed.xml"></head></html>', 200),
            '*' => Http::response('', 404),
        ]);
        $bookmark = Bookmark::factory()->create(['url' => 'https://blog.example.com', 'status' => 'pending']);

        (new ProcessBookmark($bookmark->id))->handle();

        $this->assertSame('https://blog.example.com/feed.xml', $bookmark->fresh()->feed_url);
    }

    public function test_adopt_feed_is_dispatched_only_after_feed_url_is_persisted(): void
    {
        \Illuminate\Support\Facades\Queue::fake();
        Storage::fake('public');
        Http::fake([
            'https://blog.example.com' => Http::response(
                '<html><head><link rel="alternate" type="application/rss+xml" href="/feed.xml"></head></html>', 200),
            '*' => Http::response('', 404),
        ]);
        $bookmark = Bookmark::factory()->create(['url' => 'https://blog.example.com', 'status' => 'pending']);

        (new ProcessBookmark($bookmark->id))->handle();

        // feed_url is persisted, and the adopt job (which reads it from the DB)
        // was queued — proving dispatch happens after the write, not before.
        $this->assertSame('https://blog.example.com/feed.xml', $bookmark->fresh()->feed_url);
        \Illuminate\Support\Facades\Queue::assertPushed(
            \App\Jobs\Rss\AdoptBookmarkFeed::class,
            fn (\App\Jobs\Rss\AdoptBookmarkFeed $job) => $job->bookmarkId === $bookmark->id
        );
    }

    public function test_rejects_non_http_schemes(): void
    {
        $bookmark = Bookmark::factory()->create(['url' => 'ftp://example.com', 'status' => 'pending']);

        (new ProcessBookmark($bookmark->id))->handle();

        $this->assertSame('dead', $bookmark->fresh()->status);
    }
}
