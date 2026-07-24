<?php

namespace Tests\Feature\Rss;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class EndpointThrottleTest extends TestCase
{
    use RefreshDatabase;

    public function test_discover_is_rate_limited(): void
    {
        Http::fake(['*' => Http::response('<html><head></head></html>', 200, ['Content-Type' => 'text/html'])]);
        $user = User::factory()->create();

        // throttle:6,1 — the first six requests pass the limiter, the seventh 429s.
        for ($i = 0; $i < 6; $i++) {
            $this->actingAs($user)
                ->postJson('/rss/discover', ['url' => 'https://example.com'])
                ->assertStatus(422); // no feed link found, but past the limiter
        }

        $this->actingAs($user)
            ->postJson('/rss/discover', ['url' => 'https://example.com'])
            ->assertStatus(429);
    }

    public function test_refresh_all_is_rate_limited(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 4; $i++) {
            $this->actingAs($user)->post('/rss/feeds/refresh-all')->assertRedirect();
        }

        $this->actingAs($user)->post('/rss/feeds/refresh-all')->assertStatus(429);
    }
}
