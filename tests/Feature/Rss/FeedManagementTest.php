<?php

namespace Tests\Feature\Rss;

use App\Models\RssFeed;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeedManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_partial_update_without_url_saves_and_keeps_interval(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create([
            'refresh_interval_minutes' => 120,
            'enabled' => true,
        ]);

        // The edit modal sends title/folder/interval/enabled — never url.
        $this->actingAs($user)
            ->patch("/rss/feeds/{$feed->id}", [
                'title' => 'Renamed',
                'folder' => 'Tech',
                'refresh_interval_minutes' => 120,
                'enabled' => false,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $feed->refresh();
        $this->assertSame('Renamed', $feed->title);
        $this->assertFalse((bool) $feed->enabled);
        $this->assertSame(120, (int) $feed->refresh_interval_minutes);
    }

    public function test_index_payload_carries_refresh_interval_for_the_edit_modal(): void
    {
        $user = User::factory()->create();
        RssFeed::factory()->for($user)->create(['refresh_interval_minutes' => 45]);

        $this->actingAs($user)->get('/rss')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Rss/Index')
                ->where('feeds.0.refresh_interval_minutes', 45)
            );
    }
}
