<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\FileVersion;
use App\Models\User;
use App\Services\FileVersioning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileVersioningTest extends TestCase
{
    use RefreshDatabase;

    public function test_last_snapshot_at_returns_carbon_instance(): void
    {
        // ME-05: must be Carbon|null, not a raw string/DateTime that downstream
        // callers can't safely chain (->lt, ->diffInMinutes) onto.
        $user = User::factory()->create();
        $file = File::factory()->for($user, 'owner')->create(['name' => 'a.md', 'parent_id' => null]);
        FileVersion::create([
            'file_id' => $file->id,
            'version' => 2,
            'name' => 'a.md',
            'path' => 'a.md',
            'disk' => 'public',
            'size' => 10,
            'hash' => 'x',
            'created_by' => $user->id,
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ]);

        $at = app(FileVersioning::class)->lastSnapshotAt($file);

        $this->assertInstanceOf(Carbon::class, $at);
        $this->assertLessThan(now(), $at);
    }

    public function test_last_snapshot_at_returns_null_when_no_versions(): void
    {
        $user = User::factory()->create();
        $file = File::factory()->for($user, 'owner')->create(['name' => 'a.md', 'parent_id' => null]);

        $this->assertNull(app(FileVersioning::class)->lastSnapshotAt($file));
    }

    public function test_restore_copies_the_version_blob_to_a_fresh_path(): void
    {
        Storage::fake('public');
        config(['filemanager.disk' => 'public']);
        $user = User::factory()->create();

        Storage::disk('public')->put('uploads/'.$user->id.'/current.bin', 'CURRENT');
        Storage::disk('public')->put('uploads/'.$user->id.'/v1.bin', 'OLD-CONTENT');

        $file = File::factory()->for($user, 'owner')->create([
            'name' => 'doc.bin',
            'path' => 'uploads/'.$user->id.'/current.bin',
            'disk' => 'public',
            'size' => 7,
            'version' => 2,
        ]);
        $version = FileVersion::create([
            'file_id' => $file->id,
            'version' => 1,
            'name' => 'doc.bin',
            'path' => 'uploads/'.$user->id.'/v1.bin',
            'disk' => 'public',
            'mime' => 'application/octet-stream',
            'size' => 11,
            'hash' => hash('sha256', 'OLD-CONTENT'),
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->post(route('files.versions.restore', ['file' => $file->id, 'version' => $version->id]))
            ->assertRedirect();

        $file->refresh();
        // The live file must NOT alias the version's blob path.
        $this->assertNotSame($version->path, $file->path);
        Storage::disk('public')->assertExists($file->path);
        $this->assertSame('OLD-CONTENT', Storage::disk('public')->get($file->path));
        // The version's own blob is untouched (still there, still distinct).
        Storage::disk('public')->assertExists($version->path);
    }
}
