<?php

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Models\File;
use App\Models\RssFeed;
use App\Models\RssItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QuickSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_quick_search_requires_authentication(): void
    {
        $this->getJson('/search/quick?q=hello')->assertUnauthorized();
    }

    public function test_all_scope_returns_every_group(): void
    {
        $user = User::factory()->create();
        $feed = RssFeed::factory()->for($user)->create();

        File::factory()->for($user, 'owner')->create(['name' => 'zebrafish-report.pdf', 'mime' => 'application/pdf']);
        File::factory()->for($user, 'owner')->create(['name' => 'zebrafish-notes', 'mime' => 'text/markdown']);
        RssItem::factory()->create(['user_id' => $user->id, 'feed_id' => $feed->id, 'title' => 'zebrafish weekly']);
        Bookmark::factory()->create(['owner_id' => $user->id, 'title' => 'zebrafish wiki']);

        $this->actingAs($user)->getJson('/search/quick?q=zebrafish&scope=all')
            ->assertOk()
            ->assertJsonPath('scope', 'all')
            ->assertJsonCount(1, 'results.files')
            ->assertJsonCount(1, 'results.notes')
            ->assertJsonCount(1, 'results.rss')
            ->assertJsonCount(1, 'results.bookmarks');
    }

    public function test_files_scope_excludes_notes_and_other_groups(): void
    {
        $user = User::factory()->create();
        File::factory()->for($user, 'owner')->create(['name' => 'quokka-report.pdf', 'mime' => 'application/pdf']);
        File::factory()->for($user, 'owner')->create(['name' => 'quokka-note', 'mime' => 'text/markdown']);

        $response = $this->actingAs($user)->getJson('/search/quick?q=quokka&scope=files')->assertOk();

        $response->assertJsonPath('scope', 'files')->assertJsonCount(1, 'results.files');
        $this->assertArrayNotHasKey('notes', $response->json('results'));
        $this->assertArrayNotHasKey('rss', $response->json('results'));
    }

    public function test_notes_scope_returns_markdown_files_linking_to_notes(): void
    {
        $user = User::factory()->create();
        $note = File::factory()->for($user, 'owner')->create(['name' => 'wombat-journal', 'mime' => 'text/markdown']);

        $this->actingAs($user)->getJson('/search/quick?q=wombat&scope=notes')
            ->assertOk()
            ->assertJsonCount(1, 'results.notes')
            ->assertJsonPath('results.notes.0.id', $note->id)
            ->assertJsonPath('results.notes.0.url', route('notes.index', ['open' => $note->id]));
    }

    public function test_people_scope_searches_the_directory(): void
    {
        $user = User::factory()->create();
        User::factory()->create(['name' => 'Axolotl Jones', 'title' => 'Engineer']);
        User::factory()->create(['name' => 'Someone Else', 'department' => 'Axolotl Care']);

        $response = $this->actingAs($user)->getJson('/search/quick?q=axolotl&scope=people')->assertOk();

        $response->assertJsonPath('scope', 'people')->assertJsonCount(2, 'results.people');
        $this->assertStringStartsWith('/directory/', $response->json('results.people.0.url'));
        $this->assertArrayNotHasKey('files', $response->json('results'));
    }

    public function test_all_scope_includes_people(): void
    {
        $user = User::factory()->create();
        User::factory()->create(['name' => 'Quagga Smith']);

        $this->actingAs($user)->getJson('/search/quick?q=quagga&scope=all')
            ->assertOk()
            ->assertJsonCount(1, 'results.people');
    }

    public function test_results_are_scoped_to_the_acting_user(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        File::factory()->for($other, 'owner')->create(['name' => 'platypus-secret.pdf', 'mime' => 'application/pdf']);

        $this->actingAs($user)->getJson('/search/quick?q=platypus&scope=files')
            ->assertOk()
            ->assertJsonCount(0, 'results.files');
    }

    public function test_empty_query_returns_no_groups(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/search/quick?q=&scope=all')
            ->assertOk()
            ->assertExactJson(['scope' => 'all', 'results' => []]);
    }

    public function test_invalid_scope_falls_back_to_all(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/search/quick?q=x&scope=bogus')
            ->assertOk()
            ->assertJsonPath('scope', 'all');
    }
}
