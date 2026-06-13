<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\FileVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EditContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_saving_content_writes_new_bytes_and_versions_the_old(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Storage::disk('public')->put($path = 'uploads/'.$user->id.'/note.md', '# old');
        $file = File::factory()->for($user, 'owner')->create([
            'name' => 'note.md', 'path' => $path, 'mime' => 'text/markdown', 'version' => 1,
        ]);

        $this->actingAs($user)
            ->put(route('files.content', $file), ['content' => "# new\n\nbody"])
            ->assertRedirect();

        $file->refresh();
        $this->assertSame(2, $file->version);
        $this->assertNotSame($path, $file->path);
        $this->assertSame("# new\n\nbody", Storage::disk('public')->get($file->path));
        // Old content kept as version 1.
        $this->assertTrue(FileVersion::where('file_id', $file->id)->where('version', 1)->exists());
        Storage::disk('public')->assertExists($path);
    }

    public function test_editing_an_imported_file_forks_it_to_owned(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $file = File::factory()->for($user, 'owner')->create([
            'name' => 'readme.txt', 'path' => 'readme.txt', 'disk' => 'public',
            'referenced' => true, 'mime' => 'text/plain',
        ]);
        Storage::disk('public')->put('readme.txt', 'src');

        $this->actingAs($user)->put(route('files.content', $file), ['content' => 'edited']);

        $file->refresh();
        $this->assertFalse($file->referenced);
        $this->assertSame('edited', Storage::disk('public')->get($file->path));
        Storage::disk('public')->assertExists('readme.txt'); // original source untouched
    }

    public function test_can_create_a_new_markdown_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('files.text'), [
            'name' => 'notes',
            'content' => '# Title',
            'parent_id' => null,
        ])->assertRedirect();

        $file = File::where('owner_id', $user->id)->where('name', 'notes.md')->firstOrFail();
        $this->assertSame('# Title', Storage::disk('public')->get($file->path));
        $this->assertSame('text/markdown', $file->mime);
    }

    public function test_new_file_rejects_name_collision(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        File::factory()->for($user, 'owner')->create(['name' => 'taken.md', 'parent_id' => null]);

        $this->actingAs($user)->post(route('files.text'), [
            'name' => 'taken.md', 'content' => 'x', 'parent_id' => null,
        ])->assertSessionHasErrors('name');
    }

    public function test_saving_records_a_commit_note_on_the_archived_version(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Storage::disk('public')->put($path = 'uploads/'.$user->id.'/note.md', 'old');
        $file = File::factory()->for($user, 'owner')->create([
            'name' => 'note.md', 'path' => $path, 'mime' => 'text/markdown', 'version' => 3,
        ]);

        $this->actingAs($user)
            ->put(route('files.content', $file), ['content' => 'new', 'note' => 'Fixed the intro'])
            ->assertRedirect();

        $this->assertSame('Fixed the intro', FileVersion::where('file_id', $file->id)->where('version', 3)->value('note'));
    }

    public function test_saving_a_binary_upload_versions_the_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Storage::disk('public')->put($path = 'uploads/'.$user->id.'/sheet.xlsx', 'OLDXLSX');
        $file = File::factory()->for($user, 'owner')->create([
            'name' => 'sheet.xlsx', 'path' => $path, 'version' => 1,
            'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);

        $upload = \Illuminate\Http\UploadedFile::fake()->createWithContent('sheet.xlsx', 'NEWXLSXBYTES');

        $this->actingAs($user)
            ->put(route('files.content', $file), ['file' => $upload, 'note' => 'recalc'])
            ->assertRedirect();

        $file->refresh();
        $this->assertSame(2, $file->version);
        $this->assertSame('NEWXLSXBYTES', Storage::disk('public')->get($file->path));
    }

    public function test_editor_page_renders_for_an_office_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $file = File::factory()->for($user, 'owner')->create([
            'name' => 'budget.xlsx', 'version' => 2,
            'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);

        $this->actingAs($user)->get(route('files.edit', $file))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Files/Editor')
                ->where('file.type', 'xlsx')
                ->where('file.version', 2)
                ->has('file.raw_url'));
    }

    public function test_cannot_open_editor_for_a_folder(): void
    {
        $user = User::factory()->create();
        $folder = File::factory()->for($user, 'owner')->folder()->create();
        $this->actingAs($user)->get(route('files.edit', $folder))->assertNotFound();
    }

    public function test_cannot_edit_a_folder_or_another_users_file(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $folder = File::factory()->for($owner, 'owner')->folder()->create();
        $file = File::factory()->for($owner, 'owner')->create();

        $this->actingAs($owner)->put(route('files.content', $folder), ['content' => 'x'])->assertNotFound();
        $this->actingAs($other)->put(route('files.content', $file), ['content' => 'x'])->assertForbidden();
    }
}
