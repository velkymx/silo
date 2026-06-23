<?php

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookmarkTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_shows_own_and_shared_bookmarks_only(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        Bookmark::factory()->for($user, 'owner')->create(['title' => 'Mine']);
        Bookmark::factory()->for($other, 'owner')->shared()->create(['title' => 'Company']);
        Bookmark::factory()->for($other, 'owner')->create(['title' => 'Theirs']);

        $this->actingAs($user)->get(route('bookmarks.index'))
            ->assertInertia(fn ($page) => $page->component('Bookmarks/Index')
                ->has('bookmarks', 2));
    }

    public function test_store_creates_a_bookmark_for_the_owner(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('bookmarks.store'), [
            'title' => 'Wiki', 'url' => 'https://wiki.example.com', 'category' => 'Docs',
        ])->assertRedirect();

        $this->assertDatabaseHas('bookmarks', ['title' => 'Wiki', 'owner_id' => $user->id]);
    }

    public function test_store_rejects_an_invalid_url(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('bookmarks.store'), ['title' => 'Bad', 'url' => 'not-a-url'])
            ->assertSessionHasErrors('url');
    }

    public function test_go_increments_clicks_and_redirects(): void
    {
        $user = User::factory()->create();
        $bookmark = Bookmark::factory()->for($user, 'owner')->create(['url' => 'https://tool.example.com', 'click_count' => 4]);

        $this->actingAs($user)->get(route('bookmarks.go', $bookmark))
            ->assertRedirect('https://tool.example.com');

        $this->assertSame(5, $bookmark->fresh()->click_count);
        $this->assertDatabaseHas('audit_logs', ['action' => 'bookmark.open']);
    }

    public function test_owner_can_update_and_delete(): void
    {
        $user = User::factory()->create();
        $bookmark = Bookmark::factory()->for($user, 'owner')->create();

        $this->actingAs($user)->put(route('bookmarks.update', $bookmark), [
            'title' => 'Renamed', 'url' => 'https://x.example.com',
        ])->assertRedirect();
        $this->assertSame('Renamed', $bookmark->fresh()->title);

        $this->actingAs($user)->delete(route('bookmarks.destroy', $bookmark))->assertRedirect();
        $this->assertModelMissing($bookmark);
    }

    public function test_non_owner_cannot_update_or_delete(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $bookmark = Bookmark::factory()->for($owner, 'owner')->create();

        $this->actingAs($other)->put(route('bookmarks.update', $bookmark), [
            'title' => 'Hijack', 'url' => 'https://x.example.com',
        ])->assertForbidden();
        $this->actingAs($other)->delete(route('bookmarks.destroy', $bookmark))->assertForbidden();
    }

    public function test_cannot_open_a_private_bookmark_of_another_user(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $bookmark = Bookmark::factory()->for($owner, 'owner')->create();

        $this->actingAs($other)->get(route('bookmarks.go', $bookmark))->assertForbidden();
    }
}
