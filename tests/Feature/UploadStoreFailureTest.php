<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadStoreFailureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        // Re-affirm the disk (TestCase setUp already set 'public' for filemanager).
        // Replace the 'public' disk with a stub that always returns false from
        // putFileAs() so we exercise the false-return branch of $upload->store().
        $real = Storage::fake('public');
        $wrapped = new class($real) {
            public function __construct(private $inner) {}
            public function putFileAs(string $path, $file, string $name, array $options = []): string|false {
                return false;
            }
            public function __call($name, $args) { return $this->inner->$name(...$args); }
        };
        Storage::shouldReceive('disk')->with('public')->andReturn($wrapped);
    }

    public function test_files_upload_throws_validation_when_store_returns_false(): void
    {
        $user = User::factory()->create();
        $folder = File::factory()->for($user, 'owner')->folder()->create(['name' => 'Docs', 'parent_id' => null]);

        $this->actingAs($user)
            ->postJson(route('files.upload'), [
                'files' => [UploadedFile::fake()->create('a.txt', 1)],
                'parent_id' => $folder->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['files']);

        $this->assertDatabaseMissing('files', ['name' => 'a.txt']);
    }

    public function test_photos_upload_throws_validation_when_store_returns_false(): void
    {
        // ME-01: same fix pattern as FileController — guard the store() return.
        $user = User::factory()->create();
        $folder = File::factory()->for($user, 'owner')->folder()->create(['name' => 'Photos', 'parent_id' => null, 'path' => 'Photos']);

        $this->actingAs($user)
            ->postJson('/photos/upload', [
                'files' => [UploadedFile::fake()->image('a.png')],
                'parent_id' => $folder->id,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['files']);

        $this->assertDatabaseMissing('files', ['name' => 'a.png']);
    }
}
