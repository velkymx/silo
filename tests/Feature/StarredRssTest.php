<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\RssFeed;
use App\Models\RssItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StarredRssTest extends TestCase
{
    use RefreshDatabase;

    public function test_starred_route_includes_starred_rss_items(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create(['title' => 'Laravel News']);
        $starred = RssItem::factory()->create([
            'feed_id' => $feed->id,
            'user_id' => $user->id,
            'title' => 'Laravel 12 released',
            'is_starred' => true,
            'starred_at' => now(),
        ]);
        RssItem::factory()->create([
            'feed_id' => $feed->id,
            'user_id' => $user->id,
            'title' => 'Not starred',
            'is_starred' => false,
        ]);

        $this->actingAs($user)->get('/starred')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Files/Index')
                ->where('starredOnly', true)
                ->has('rssItems', 1)
                ->where('rssItems.0.id', $starred->id)
                ->where('rssItems.0.title', 'Laravel 12 released')
                ->where('rssItems.0.feed_title', 'Laravel News')
                ->where('rssItems.0.is_starred', true)
            );
    }

    public function test_non_starred_route_does_not_include_rss_items(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        RssItem::factory()->create([
            'feed_id' => $feed->id,
            'user_id' => $user->id,
            'is_starred' => true,
            'starred_at' => now(),
        ]);

        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Files/Index')
                ->where('rssItems', [])
            );
    }

    public function test_starred_route_excludes_other_users_rss_items(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $feed = RssFeed::factory()->for($other)->create();
        RssItem::factory()->create([
            'feed_id' => $feed->id,
            'user_id' => $other->id,
            'is_starred' => true,
            'starred_at' => now(),
        ]);

        $this->actingAs($me)->get('/starred')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Files/Index')
                ->where('rssItems', [])
            );
    }

    public function test_starred_rss_items_appear_alongside_starred_files(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        File::factory()->for($user, 'owner')->create(['name' => 'fav.txt', 'starred' => true]);
        RssItem::factory()->create([
            'feed_id' => $feed->id,
            'user_id' => $user->id,
            'is_starred' => true,
            'starred_at' => now(),
        ]);

        $this->actingAs($user)->get('/starred')
            ->assertOk()
            ->assertInertia(fn (Assert $p) => $p
                ->component('Files/Index')
                ->has('files', 1)
                ->has('rssItems', 1)
            );
    }
}
