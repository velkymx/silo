<?php

namespace Tests\Feature;

use App\Jobs\ProcessUploadedFile;
use App\Jobs\SyncNoteLinks;
use App\Models\File;
use App\Models\FileVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NoteAutosaveTest extends TestCase
{
    use RefreshDatabase;

    private function note(User $user, array $overrides = []): File
    {
        Storage::disk('public')->put($path = 'uploads/'.$user->id.'/working.md', '# old');

        return File::factory()->for($user, 'owner')->create(array_merge([
            'name' => 'Note.md', 'path' => $path, 'disk' => 'public',
            'mime' => 'text/markdown', 'version' => 1, 'hash' => hash('sha256', '# old'),
        ], $overrides));
    }

    public function test_autosave_overwrites_in_place_without_versioning(): void
    {
        Bus::fake();
        Storage::fake('public');
        $user = User::factory()->create();
        $note = $this->note($user, ['created_at' => now()]);
        $originalPath = $note->path;

        $this->actingAs($user)
            ->put(route('notes.autosave', $note), ['content' => '# new body'])
            ->assertOk()
            ->assertJsonStructure(['saved_at', 'version']);

        $note->refresh();
        $this->assertSame(1, $note->version, 'autosave must not bump the version');
        $this->assertSame($originalPath, $note->path, 'autosave overwrites the working blob in place');
        $this->assertSame('# new body', Storage::disk('public')->get($note->path));
        $this->assertSame(0, FileVersion::where('file_id', $note->id)->count());
    }

    public function test_autosave_snapshots_after_the_interval_elapses(): void
    {
        Bus::fake();
        Storage::fake('public');
        $user = User::factory()->create();
        // Created well beyond the snapshot interval with no prior versions.
        $note = $this->note($user, ['created_at' => now()->subMinutes(30)]);
        $oldPath = $note->path;

        $this->actingAs($user)
            ->put(route('notes.autosave', $note), ['content' => '# checkpointed'])
            ->assertOk();

        $note->refresh();
        $this->assertSame(2, $note->version);
        $this->assertNotSame($oldPath, $note->path);
        $this->assertSame('# checkpointed', Storage::disk('public')->get($note->path));
        // The archived version preserves the prior blob.
        $this->assertTrue(FileVersion::where('file_id', $note->id)->where('version', 1)->exists());
        Storage::disk('public')->assertExists($oldPath);
    }

    public function test_checkpoint_forces_a_version_within_the_interval(): void
    {
        Bus::fake();
        Storage::fake('public');
        $user = User::factory()->create();
        // Fresh note (well inside the interval) — only an explicit checkpoint snapshots.
        $note = $this->note($user, ['created_at' => now()]);

        $this->actingAs($user)
            ->put(route('notes.autosave', $note), ['content' => '# milestone', 'checkpoint' => true, 'note' => 'v1'])
            ->assertOk();

        $note->refresh();
        $this->assertSame(2, $note->version);
        $this->assertSame('v1', FileVersion::where('file_id', $note->id)->where('version', 1)->value('note'));
    }

    public function test_autosave_dispatches_processing_and_link_sync(): void
    {
        Bus::fake();
        Storage::fake('public');
        $user = User::factory()->create();
        $note = $this->note($user, ['created_at' => now()]);

        $this->actingAs($user)->put(route('notes.autosave', $note), ['content' => 'x'])->assertOk();

        Bus::assertDispatched(ProcessUploadedFile::class);
        Bus::assertDispatched(SyncNoteLinks::class);
    }

    public function test_autosave_rejects_non_markdown(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $file = File::factory()->for($user, 'owner')->create(['mime' => 'text/plain']);

        $this->actingAs($user)->put(route('notes.autosave', $file), ['content' => 'x'])->assertNotFound();
    }

    public function test_autosave_forbidden_for_other_users(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $note = $this->note($owner);

        $this->actingAs($other)->put(route('notes.autosave', $note), ['content' => 'x'])->assertForbidden();
    }
}
