<?php

namespace Tests\Feature\Rss;

use App\Models\AuditLog;
use App\Models\RssFeed;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_changing_url_clears_http_cache_and_health_state(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create([
            'url' => 'https://old.example.com/feed.xml',
            'etag' => 'W/"abc"',
            'last_modified' => 'Mon, 06 Jul 2026 12:00:00 GMT',
            'last_http_status' => 200,
            'consecutive_failures' => 3,
            'last_error' => 'boom',
        ]);

        $this->actingAs($user)
            ->patch("/rss/feeds/{$feed->id}", [
                'title' => $feed->title,
                'url' => 'https://new.example.com/feed.xml',
            ])
            ->assertRedirect();

        $feed->refresh();
        $this->assertSame('https://new.example.com/feed.xml', $feed->url);
        $this->assertNull($feed->etag);
        $this->assertNull($feed->last_modified);
        $this->assertNull($feed->last_http_status);
        $this->assertNull($feed->last_error);
        $this->assertSame(0, $feed->consecutive_failures);
    }

    public function test_update_without_url_change_keeps_cache_and_logs(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create([
            'etag' => 'W/"keep"',
            'consecutive_failures' => 2,
        ]);

        $this->actingAs($user)
            ->patch("/rss/feeds/{$feed->id}", ['title' => 'Renamed'])
            ->assertRedirect();

        $feed->refresh();
        $this->assertSame('Renamed', $feed->title);
        $this->assertSame('W/"keep"', $feed->etag);
        $this->assertSame(2, $feed->consecutive_failures);
        $this->assertSame(1, AuditLog::where('action', 'rss.feed.update')->count());
    }
}
