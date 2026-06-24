<?php

namespace Tests\Feature;

use App\Models\Bookmark;
use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StarredPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_aggregates_starred_notes_bookmarks_and_files(): void
    {
        $user = User::factory()->create();
        File::factory()->for($user, 'owner')->create(['name' => 'Note.md', 'mime' => 'text/markdown', 'starred' => true]);
        File::factory()->for($user, 'owner')->create(['name' => 'Plain.md', 'mime' => 'text/markdown', 'starred' => false]);
        File::factory()->for($user, 'owner')->create(['name' => 'doc.pdf', 'mime' => 'application/pdf', 'starred' => true]);
        Bookmark::factory()->for($user, 'owner')->create(['starred' => true]);
        Bookmark::factory()->for($user, 'owner')->create(['starred' => false]);

        $this->actingAs($user)->get(route('starred.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Starred/Index')
                ->has('notes', 1)
                ->has('bookmarks', 1)
                ->has('files', 1));
    }

    public function test_only_shows_the_users_own_starred_items(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        File::factory()->for($other, 'owner')->create(['name' => 'Theirs.md', 'mime' => 'text/markdown', 'starred' => true]);

        $this->actingAs($user)->get(route('starred.index'))
            ->assertInertia(fn ($page) => $page->has('notes', 0));
    }
}
