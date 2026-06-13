<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File as Filesystem;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportFolderTest extends TestCase
{
    use RefreshDatabase;

    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        // A real on-disk source directory the import reads in place.
        $this->root = storage_path('app/testimport-'.uniqid());
        Filesystem::ensureDirectoryExists($this->root.'/Photos');
        file_put_contents($this->root.'/readme.txt', 'hello');
        file_put_contents($this->root.'/Photos/a.txt', 'photo a');

        config(['filesystems.disks.srcimport' => ['driver' => 'local', 'root' => $this->root]]);
        Storage::fake('public'); // thumbnail/app disk
    }

    protected function tearDown(): void
    {
        Filesystem::deleteDirectory($this->root);
        parent::tearDown();
    }

    public function test_imports_a_server_folder_in_place(): void
    {
        $user = User::factory()->create(['email' => 'owner@example.com']);

        $this->artisan('files:import', [
            '--disk' => 'srcimport',
            '--owner' => 'owner@example.com',
            '--name' => 'Library',
            '--no-process' => true,
        ])->assertSuccessful();

        // Wrapping folder + subfolder created, referencing the source disk.
        $top = File::where('owner_id', $user->id)->where('name', 'Library')->where('is_dir', true)->firstOrFail();
        $this->assertDatabaseHas('files', ['parent_id' => $top->id, 'name' => 'Photos', 'is_dir' => true]);

        $readme = File::where('owner_id', $user->id)->where('name', 'readme.txt')->firstOrFail();
        $this->assertTrue($readme->referenced);
        $this->assertSame('srcimport', $readme->disk);
        $this->assertSame('readme.txt', $readme->path);
        $this->assertSame(5, $readme->size);

        $photos = File::where('name', 'Photos')->firstOrFail();
        $this->assertDatabaseHas('files', ['parent_id' => $photos->id, 'name' => 'a.txt', 'referenced' => true]);
    }

    public function test_import_is_idempotent(): void
    {
        $user = User::factory()->create();
        $args = ['--disk' => 'srcimport', '--owner' => $user->id, '--name' => 'Library', '--no-process' => true];

        $this->artisan('files:import', $args)->assertSuccessful();
        $this->artisan('files:import', $args)->assertSuccessful();

        // No duplicate rows on re-import.
        $this->assertSame(1, File::where('owner_id', $user->id)->where('name', 'readme.txt')->count());
        $this->assertSame(1, File::where('owner_id', $user->id)->where('name', 'Library')->count());
    }

    public function test_purging_an_imported_file_keeps_the_source(): void
    {
        $user = User::factory()->create();
        $this->artisan('files:import', ['--disk' => 'srcimport', '--owner' => $user->id, '--no-process' => true]);

        $file = File::where('owner_id', $user->id)->where('name', 'readme.txt')->firstOrFail();
        $file->delete();
        app(\App\Services\TrashService::class)->purge($file);

        // Row gone, but the real source file is untouched.
        $this->assertDatabaseMissing('files', ['id' => $file->id]);
        $this->assertFileExists($this->root.'/readme.txt');
    }

    public function test_imported_file_is_downloadable(): void
    {
        $user = User::factory()->create();
        $this->artisan('files:import', ['--disk' => 'srcimport', '--owner' => $user->id, '--no-process' => true]);
        $file = File::where('owner_id', $user->id)->where('name', 'readme.txt')->firstOrFail();

        $this->actingAs($user)->get(route('files.download', $file))->assertOk();
    }
}
