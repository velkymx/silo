<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\FileVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileVersioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_uploading_same_name_creates_a_version_and_keeps_old_blob(): void
    {
        Storage::fake('public');
        Queue::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('files.upload'), [
            'files' => [UploadedFile::fake()->createWithContent('report.txt', 'v1')],
        ]);

        $file = File::where('owner_id', $user->id)->where('name', 'report.txt')->firstOrFail();
        $oldPath = $file->path;
        $this->assertSame(1, $file->version);

        $this->actingAs($user)->post(route('files.upload'), [
            'files' => [UploadedFile::fake()->createWithContent('report.txt', 'v2-longer')],
        ]);

        // Still one current file, now at version 2.
        $this->assertSame(1, File::where('owner_id', $user->id)->where('name', 'report.txt')->count());
        $file->refresh();
        $this->assertSame(2, $file->version);
        $this->assertNotSame($oldPath, $file->path);

        // The previous blob is archived as version 1 and still on disk.
        $version = FileVersion::where('file_id', $file->id)->where('version', 1)->firstOrFail();
        $this->assertSame($oldPath, $version->path);
        Storage::disk('public')->assertExists($oldPath);
        Storage::disk('public')->assertExists($file->path);
    }

    public function test_restore_brings_back_old_version_and_archives_current(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('files.upload'), [
            'files' => [UploadedFile::fake()->createWithContent('a.txt', 'one')],
        ]);
        $this->actingAs($user)->post(route('files.upload'), [
            'files' => [UploadedFile::fake()->createWithContent('a.txt', 'two')],
        ]);

        $file = File::where('owner_id', $user->id)->where('name', 'a.txt')->firstOrFail();
        $v1 = FileVersion::where('file_id', $file->id)->where('version', 1)->firstOrFail();

        $this->actingAs($user)
            ->post(route('files.versions.restore', [$file->id, $v1->id]))
            ->assertRedirect();

        $file->refresh();
        $this->assertSame(3, $file->version);
        $this->assertSame($v1->path, $file->path);
        // The content that was current (version 2) is now archived too.
        $this->assertTrue(FileVersion::where('file_id', $file->id)->where('version', 2)->exists());
    }

    public function test_can_download_a_specific_version(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $file = File::factory()->for($user, 'owner')->create(['name' => 'doc.txt']);
        Storage::disk('public')->put($path = 'uploads/'.$user->id.'/old', 'old-bytes');
        $version = FileVersion::factory()->create([
            'file_id' => $file->id,
            'version' => 1,
            'name' => 'doc.txt',
            'path' => $path,
            'disk' => 'public',
        ]);

        $this->actingAs($user)
            ->get(route('files.versions.download', [$file->id, $version->id]))
            ->assertOk()
            ->assertDownload('doc.txt');
    }

    public function test_cannot_touch_versions_of_another_users_file(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $file = File::factory()->for($owner, 'owner')->create();
        $version = FileVersion::factory()->create(['file_id' => $file->id, 'version' => 1]);

        $this->actingAs($other)
            ->post(route('files.versions.restore', [$file->id, $version->id]))
            ->assertForbidden();
    }

    public function test_version_must_belong_to_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $fileA = File::factory()->for($user, 'owner')->create();
        $fileB = File::factory()->for($user, 'owner')->create();
        $version = FileVersion::factory()->create(['file_id' => $fileB->id, 'version' => 1]);

        $this->actingAs($user)
            ->post(route('files.versions.restore', [$fileA->id, $version->id]))
            ->assertNotFound();
    }
}
