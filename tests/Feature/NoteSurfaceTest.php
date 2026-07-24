<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\NoteLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NoteSurfaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lazily_creates_the_notes_root_and_renders(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('notes.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Notes/Index')->has('rootId')->has('notes'));

        $this->assertDatabaseHas('files', [
            'owner_id' => $user->id, 'name' => 'Notes', 'is_dir' => true, 'parent_id' => null,
        ]);
    }

    public function test_index_lists_only_markdown_notes_under_the_root(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('notes.index')); // create root
        $root = File::where('owner_id', $user->id)->where('name', 'Notes')->firstOrFail();

        File::factory()->for($user, 'owner')->create(['name' => 'Kept.md', 'mime' => 'text/markdown', 'parent_id' => $root->id]);
        File::factory()->for($user, 'owner')->create(['name' => 'pic.png', 'mime' => 'image/png', 'parent_id' => $root->id]);

        $this->actingAs($user)->get(route('notes.index'))
            ->assertInertia(fn ($page) => $page->has('notes', 1)
                ->where('notes.0.title', 'Kept'));
    }

    public function test_store_creates_a_markdown_note_under_the_root(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('notes.store'), ['name' => 'My Idea', 'content' => '# Hi'])
            ->assertRedirect();

        $note = File::where('owner_id', $user->id)->where('name', 'My Idea.md')->firstOrFail();
        $this->assertSame('text/markdown', $note->mime);
        $this->assertSame('# Hi', Storage::disk('public')->get($note->path));
        $root = File::where('owner_id', $user->id)->where('name', 'Notes')->firstOrFail();
        $this->assertSame($root->id, $note->parent_id);
    }

    public function test_create_folder_under_the_notes_root(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('notes.index'));
        $root = File::where('owner_id', $user->id)->where('name', 'Notes')->firstOrFail();

        $this->actingAs($user)->post(route('notes.folders.create'), ['name' => 'Projects'])
            ->assertRedirect();

        $this->assertDatabaseHas('files', [
            'owner_id' => $user->id, 'name' => 'Projects', 'is_dir' => true, 'parent_id' => $root->id,
        ]);
    }

    public function test_store_places_a_note_in_the_given_folder(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('notes.index'));
        $root = File::where('owner_id', $user->id)->where('name', 'Notes')->firstOrFail();
        $folder = File::factory()->for($user, 'owner')->folder()->create(['name' => 'Sub', 'parent_id' => $root->id]);

        $this->actingAs($user)->post(route('notes.store'), ['name' => 'Nested', 'parent_id' => $folder->id])
            ->assertRedirect();

        $note = File::where('owner_id', $user->id)->where('name', 'Nested.md')->firstOrFail();
        $this->assertSame($folder->id, $note->parent_id);
    }

    public function test_store_rejects_a_folder_outside_the_notes_subtree(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('notes.index'));
        // A folder NOT under the Notes root falls back to the root.
        $outside = File::factory()->for($user, 'owner')->folder()->create(['name' => 'Elsewhere', 'parent_id' => null]);
        $root = File::where('owner_id', $user->id)->where('name', 'Notes')->firstOrFail();

        $this->actingAs($user)->post(route('notes.store'), ['name' => 'Stray', 'parent_id' => $outside->id]);

        $this->assertSame($root->id, File::where('name', 'Stray.md')->value('parent_id'));
    }

    public function test_store_opens_existing_note_instead_of_duplicating(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('notes.store'), ['name' => 'Dup']);
        $this->actingAs($user)->post(route('notes.store'), ['name' => 'Dup']);

        $this->assertSame(1, File::where('owner_id', $user->id)->where('name', 'Dup.md')->count());
    }

    public function test_rename_changes_the_title_keeping_the_md_extension(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $note = File::factory()->for($user, 'owner')->create(['name' => 'Old.md', 'mime' => 'text/markdown']);

        $this->actingAs($user)->put(route('notes.rename', $note), ['title' => 'Brand New'])
            ->assertRedirect();

        $this->assertSame('Brand New.md', $note->fresh()->name);
    }

    public function test_rename_rejects_a_duplicate_title(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        File::factory()->for($user, 'owner')->create(['name' => 'Taken.md', 'mime' => 'text/markdown', 'parent_id' => null]);
        $note = File::factory()->for($user, 'owner')->create(['name' => 'Mine.md', 'mime' => 'text/markdown', 'parent_id' => null]);

        $this->actingAs($user)->put(route('notes.rename', $note), ['title' => 'Taken'])
            ->assertSessionHasErrors('title');
    }

    public function test_search_notes_is_owner_scoped(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $other = User::factory()->create();
        File::factory()->for($user, 'owner')->create(['name' => 'Roadmap.md', 'mime' => 'text/markdown']);
        File::factory()->for($other, 'owner')->create(['name' => 'Roadmap.md', 'mime' => 'text/markdown']);

        $this->actingAs($user)->getJson(route('notes.search.notes', ['q' => 'road']))
            ->assertOk()
            ->assertJsonCount(1, 'results')
            ->assertJsonPath('results.0.title', 'Roadmap');
    }

    public function test_search_users_matches_by_name(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        User::factory()->create(['name' => 'Alice Smith']);

        $this->actingAs($user)->getJson(route('notes.search.users', ['q' => 'alice']))
            ->assertOk()
            ->assertJsonPath('results.0.name', 'Alice Smith')
            ->assertJsonPath('results.0.handle', 'alicesmith');
    }

    public function test_content_serves_a_pending_note_without_a_queue_worker(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Storage::disk('public')->put($path = 'uploads/'.$user->id.'/note.md', '# Hello');
        $note = File::factory()->for($user, 'owner')->create([
            'name' => 'Note.md', 'path' => $path, 'disk' => 'public',
            'mime' => 'text/markdown', 'status' => File::STATUS_PENDING,
        ]);

        $res = $this->actingAs($user)->get(route('notes.content', $note))->assertOk();
        $this->assertSame('# Hello', $res->streamedContent());
    }

    public function test_content_is_forbidden_for_other_users(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        Storage::disk('public')->put($path = 'uploads/'.$owner->id.'/note.md', 'secret');
        $note = File::factory()->for($owner, 'owner')->create([
            'name' => 'Note.md', 'path' => $path, 'disk' => 'public', 'mime' => 'text/markdown',
        ]);

        $this->actingAs($other)->get(route('notes.content', $note))->assertForbidden();
    }

    public function test_index_exposes_starred_state(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('notes.index'));
        $root = File::where('owner_id', $user->id)->where('name', 'Notes')->firstOrFail();
        File::factory()->for($user, 'owner')->create([
            'name' => 'Star.md', 'mime' => 'text/markdown', 'parent_id' => $root->id, 'starred' => true,
        ]);

        $this->actingAs($user)->get(route('notes.index'))
            ->assertInertia(fn ($page) => $page->where('notes.0.starred', true));
    }

    public function test_backlinks_returns_linking_sources(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $target = File::factory()->for($user, 'owner')->create(['name' => 'Target.md', 'mime' => 'text/markdown']);
        $source = File::factory()->for($user, 'owner')->create(['name' => 'Source.md', 'mime' => 'text/markdown']);
        NoteLink::create([
            'source_file_id' => $source->id, 'target_file_id' => $target->id,
            'target_title' => 'Target', 'link_text' => 'Target', 'owner_id' => $user->id,
        ]);

        $this->actingAs($user)->getJson(route('notes.backlinks', $target))
            ->assertOk()
            ->assertJsonPath('backlinks.0.id', $source->id)
            ->assertJsonPath('backlinks.0.title', 'Source');
    }
}
