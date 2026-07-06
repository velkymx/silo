<?php

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Models\File;
use App\Models\RssFeed;
use App\Models\RssItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_query_returns_empty_results(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/search?q=')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Search/Index')
                ->where('q', '')
                ->where('total', 0)
                ->where('results', ['files' => [], 'rss' => [], 'bookmarks' => []])
            );
    }

    public function test_search_finds_files(): void
    {
        $user = User::factory()->create();
        $file = File::factory()->for($user, 'owner')->create(['name' => 'unique-test-report.pdf', 'is_dir' => false]);
        $this->actingAs($user)->get('/search?q=unique-test')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Search/Index')
                ->has('results.files', 1)
                ->where('results.files.0.id', $file->id)
                ->where('results.files.0.title', 'unique-test-report.pdf')
            );
    }

    public function test_search_finds_rss_items(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        $item = RssItem::factory()->create([
            'feed_id' => $feed->id,
            'user_id' => $user->id,
            'title' => 'Laravel twelve released',
        ]);
        $this->actingAs($user)->get('/search?q=Laravel+twelve')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Search/Index')
                ->has('results.rss', 1)
                ->where('results.rss.0.id', $item->id)
                ->where('results.rss.0.title', 'Laravel twelve released')
                ->where('results.rss.0.url', "/rss/items/{$item->id}")
            );
    }

    public function test_search_finds_bookmarks(): void
    {
        $user = User::factory()->create();
        $bookmark = Bookmark::factory()->for($user, 'owner')->create(['title' => 'Cool Laravel Tips', 'url' => 'https://example.com']);
        $this->actingAs($user)->get('/search?q=Cool+Laravel')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Search/Index')
                ->has('results.bookmarks', 1)
                ->where('results.bookmarks.0.id', $bookmark->id)
            );
    }

    public function test_search_is_scoped_to_current_user(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();
        $feed = RssFeed::factory()->for($other)->create();
        RssItem::factory()->create([
            'feed_id' => $feed->id,
            'user_id' => $other->id,
            'title' => 'secret unsearchable note',
        ]);

        $this->actingAs($me)->get('/search?q=unsearchable')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Search/Index')
                ->where('total', 0)
            );
    }

    public function test_search_excludes_muted_feed_items(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();
        $feed->mute();
        RssItem::factory()->create([
            'feed_id' => $feed->id,
            'user_id' => $user->id,
            'title' => 'hidden-by-mute special',
        ]);

        $this->actingAs($user)->get('/search?q=hidden-by-mute')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Search/Index')
                ->where('results.rss', [])
            );
    }

    public function test_search_includes_feed_title_in_rss_results(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create(['title' => 'Hacker News RSS']);
        RssItem::factory()->create([
            'feed_id' => $feed->id,
            'user_id' => $user->id,
            'title' => 'Show HN: a new widget',
            'excerpt' => 'some content',
        ]);

        $response = $this->actingAs($user)->get('/search?q=widget')->assertOk();
        $rss = $response->original->getData()['page']['props']['results']['rss'];
        $this->assertNotEmpty($rss);
        $this->assertSame('Hacker News RSS', $rss[0]['meta']['feed_title']);
    }

    public function test_guest_cannot_search(): void
    {
        $this->get('/search?q=anything')->assertRedirect('/login');
    }
}
