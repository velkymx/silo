<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IndexFilesTest extends TestCase
{
    use RefreshDatabase;

    public function test_indexes_file_with_correct_hash_without_loading_into_memory(): void
    {
        Storage::fake('local');
        config(['filemanager.disk' => 'local']);

        $user = User::factory()->create();
        $content = 'hello world test content';
        Storage::disk('local')->put("uploads/{$user->id}/test.txt", $content);
        $expected = hash('sha256', $content);

        $this->artisan('files:index', ['--disk' => 'local'])->assertSuccessful();

        $file = File::where('owner_id', $user->id)->where('name', 'test.txt')->first();
        $this->assertNotNull($file, 'File should be indexed');
        $this->assertSame($expected, $file->hash);
    }

    public function test_index_is_idempotent(): void
    {
        Storage::fake('local');
        config(['filemanager.disk' => 'local']);

        $user = User::factory()->create();
        Storage::disk('local')->put("uploads/{$user->id}/doc.txt", 'content');

        $this->artisan('files:index', ['--disk' => 'local'])->assertSuccessful();
        $this->artisan('files:index', ['--disk' => 'local'])->assertSuccessful();

        $this->assertSame(1, File::where('owner_id', $user->id)->count());
    }
}
