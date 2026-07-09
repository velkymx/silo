<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileManagerTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_a_file_into_the_db_and_disk(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('files.upload'), [
            'files' => [UploadedFile::fake()->create('report.pdf', 100, 'application/pdf')],
        ]);

        $response->assertRedirect(route('files.index'));

        $file = File::where('owner_id', $user->id)->where('name', 'report.pdf')->first();
        $this->assertNotNull($file);
        $this->assertFalse($file->is_dir);
        $this->assertNotNull($file->hash);
        Storage::disk('public')->assertExists($file->path);
    }

    public function test_user_can_download_their_own_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Storage::disk('public')->put('uploads/'.$user->id.'/a.txt', 'hello');
        $file = File::factory()->for($user, 'owner')->create([
            'name' => 'a.txt',
            'path' => 'uploads/'.$user->id.'/a.txt',
        ]);

        $this->actingAs($user)
            ->get(route('files.download', $file))
            ->assertOk()
            ->assertDownload('a.txt');
    }

    public function test_user_cannot_download_another_users_file(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $file = File::factory()->for($owner, 'owner')->create();

        $this->actingAs($other)
            ->get(route('files.download', $file))
            ->assertForbidden();
    }

    public function test_user_can_delete_their_own_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Storage::disk('public')->put('uploads/'.$user->id.'/a.txt', 'hello');
        $file = File::factory()->for($user, 'owner')->create([
            'path' => 'uploads/'.$user->id.'/a.txt',
        ]);

        $this->actingAs($user)
            ->delete(route('files.delete', $file))
            ->assertRedirect();

        $this->assertSoftDeleted($file);
        // Soft-delete moves the file to trash; the blob is kept until purged.
        Storage::disk('public')->assertExists('uploads/'.$user->id.'/a.txt');
    }

    public function test_user_cannot_delete_another_users_file(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $file = File::factory()->for($owner, 'owner')->create();

        $this->actingAs($other)
            ->delete(route('files.delete', $file))
            ->assertForbidden();

        $this->assertNotSoftDeleted($file);
    }

    public function test_deleting_a_folder_cascades_to_its_contents(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $folder = File::factory()->for($user, 'owner')->folder()->create();
        $child = File::factory()->for($user, 'owner')->create(['parent_id' => $folder->id]);

        $this->actingAs($user)
            ->delete(route('files.delete', $folder))
            ->assertRedirect();

        $this->assertSoftDeleted($folder);
        $this->assertSoftDeleted($child);
    }

    public function test_guests_cannot_access_the_file_index(): void
    {
        $this->get(route('files.index'))->assertRedirect(route('login'));
    }

    public function test_index_exposes_section_counts_for_the_sidebar_nav(): void
    {
        $user = User::factory()->create();
        File::factory()->count(3)->for($user, 'owner')->create(['is_dir' => false]);
        File::factory()->for($user, 'owner')->create(['is_dir' => false, 'starred' => true]);
        $trashed = File::factory()->for($user, 'owner')->create(['is_dir' => false]);
        $trashed->delete();

        $this->actingAs($user)->get('/')->assertInertia(fn ($page) => $page
            ->component('Files/Index')
            ->where('sectionCounts.all', 4)      // 3 + 1 starred, all non-dir, non-trashed
            ->where('sectionCounts.starred', 1)
            ->where('sectionCounts.trash', 1)
        );
    }
}
