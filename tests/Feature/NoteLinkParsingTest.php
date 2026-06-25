<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\NoteLink;
use App\Models\NoteMention;
use App\Models\User;
use App\Services\NoteLinker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NoteLinkParsingTest extends TestCase
{
    use RefreshDatabase;

    private function note(User $user, string $name, string $body): File
    {
        Storage::disk('public')->put($path = 'uploads/'.$user->id.'/'.\Illuminate\Support\Str::random(40).'.md', $body);

        return File::factory()->for($user, 'owner')->create([
            'name' => $name, 'path' => $path, 'disk' => 'public', 'mime' => 'text/markdown',
        ]);
    }

    public function test_resolves_wikilink_to_an_existing_note(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $target = $this->note($user, 'Roadmap.md', '# Roadmap');
        $source = $this->note($user, 'Daily.md', 'See [[Roadmap]] for details.');

        app(NoteLinker::class)->sync($source);

        $link = NoteLink::where('source_file_id', $source->id)->firstOrFail();
        $this->assertSame($target->id, $link->target_file_id);
        $this->assertSame('Roadmap', $link->target_title);
    }

    public function test_unresolved_wikilink_is_stored_with_null_target(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $source = $this->note($user, 'Daily.md', 'Plan in [[Nonexistent]].');

        app(NoteLinker::class)->sync($source);

        $link = NoteLink::where('source_file_id', $source->id)->firstOrFail();
        $this->assertNull($link->target_file_id);
        $this->assertSame('Nonexistent', $link->target_title);
    }

    public function test_unresolved_link_lights_up_when_target_created_later(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $source = $this->note($user, 'Daily.md', 'Plan in [[Project X]].');
        app(NoteLinker::class)->sync($source);

        $target = $this->note($user, 'Project X.md', '# Project X');
        app(NoteLinker::class)->sync($target);

        $this->assertSame($target->id, NoteLink::where('source_file_id', $source->id)->value('target_file_id'));
    }

    public function test_resolves_mention_to_a_user(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $mentioned = User::factory()->create(['name' => 'Alice']);
        $note = $this->note($user, 'Standup.md', 'Ask @alice about the deploy.');

        app(NoteLinker::class)->sync($note);

        $this->assertTrue(NoteMention::where('file_id', $note->id)
            ->where('mentioned_user_id', $mentioned->id)->exists());
    }

    public function test_syncs_inline_tags_to_the_tags_relation(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $note = $this->note($user, 'Ideas.md', 'Brainstorm #todo and #project-x.');

        app(NoteLinker::class)->sync($note);

        $note->refresh();
        $this->assertEqualsCanonicalizing(['todo', 'project-x'], $note->tags->pluck('name')->all());
    }

    public function test_ignores_syntax_inside_code_blocks(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $note = $this->note($user, 'Doc.md', "```\n[[Nope]] @nobody #notag\n```\nreal text");

        app(NoteLinker::class)->sync($note);

        $this->assertSame(0, NoteLink::where('source_file_id', $note->id)->count());
        $this->assertSame(0, NoteMention::where('file_id', $note->id)->count());
        $note->refresh();
        $this->assertCount(0, $note->tags);
    }

    public function test_resync_replaces_stale_links(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->note($user, 'Roadmap.md', '# Roadmap');
        $source = $this->note($user, 'Daily.md', 'See [[Roadmap]].');
        app(NoteLinker::class)->sync($source);

        Storage::disk('public')->put($source->path, 'No links here anymore.');
        app(NoteLinker::class)->sync($source);

        $this->assertSame(0, NoteLink::where('source_file_id', $source->id)->count());
    }
}
