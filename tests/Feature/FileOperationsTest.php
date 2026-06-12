<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_rename_updates_name(): void
    {
        $user = User::factory()->create();
        $file = File::factory()->for($user, 'owner')->create(['name' => 'old.txt']);

        $this->actingAs($user)
            ->patch("/files/{$file->id}/rename", ['name' => 'new.txt'])
            ->assertRedirect();

        $this->assertSame('new.txt', $file->fresh()->name);
    }

    public function test_rename_rejects_sibling_collision(): void
    {
        $user = User::factory()->create();
        File::factory()->for($user, 'owner')->create(['name' => 'taken.txt']);
        $file = File::factory()->for($user, 'owner')->create(['name' => 'mine.txt']);

        $this->actingAs($user)
            ->patch("/files/{$file->id}/rename", ['name' => 'taken.txt'])
            ->assertSessionHasErrors('name');

        $this->assertSame('mine.txt', $file->fresh()->name);
    }

    public function test_move_reparents_into_folder(): void
    {
        $user = User::factory()->create();
        $folder = File::factory()->for($user, 'owner')->folder()->create();
        $file = File::factory()->for($user, 'owner')->create();

        $this->actingAs($user)
            ->post("/files/{$file->id}/move", ['target_id' => $folder->id])
            ->assertRedirect();

        $this->assertSame($folder->id, $file->fresh()->parent_id);
    }

    public function test_move_cannot_nest_folder_inside_itself(): void
    {
        $user = User::factory()->create();
        $parent = File::factory()->for($user, 'owner')->folder()->create();
        $child = File::factory()->for($user, 'owner')->folder()->create(['parent_id' => $parent->id]);

        $this->actingAs($user)
            ->post("/files/{$parent->id}/move", ['target_id' => $child->id])
            ->assertSessionHasErrors('target_id');

        $this->assertNull($parent->fresh()->parent_id);
    }

    public function test_copy_file_duplicates_row_and_blob(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Storage::disk('public')->put('uploads/'.$user->id.'/a.bin', 'data');
        $file = File::factory()->for($user, 'owner')->create([
            'name' => 'a.bin',
            'path' => 'uploads/'.$user->id.'/a.bin',
        ]);
        $folder = File::factory()->for($user, 'owner')->folder()->create();

        $this->actingAs($user)
            ->post("/files/{$file->id}/copy", ['target_id' => $folder->id])
            ->assertRedirect();

        $copy = File::where('parent_id', $folder->id)->where('owner_id', $user->id)->first();
        $this->assertNotNull($copy);
        $this->assertNotSame($file->path, $copy->path);
        Storage::disk('public')->assertExists($copy->path);
    }

    public function test_copy_into_same_folder_gets_unique_name(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Storage::disk('public')->put('uploads/'.$user->id.'/a.txt', 'x');
        $file = File::factory()->for($user, 'owner')->create([
            'name' => 'a.txt',
            'path' => 'uploads/'.$user->id.'/a.txt',
            'parent_id' => null,
        ]);

        $this->actingAs($user)
            ->post("/files/{$file->id}/copy", ['target_id' => null])
            ->assertRedirect();

        $this->assertDatabaseHas('files', ['owner_id' => $user->id, 'name' => 'a (copy).txt']);
    }

    public function test_copy_folder_is_deep(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $folder = File::factory()->for($user, 'owner')->folder()->create(['name' => 'src']);
        Storage::disk('public')->put('uploads/'.$user->id.'/c.txt', 'y');
        File::factory()->for($user, 'owner')->create([
            'name' => 'c.txt',
            'path' => 'uploads/'.$user->id.'/c.txt',
            'parent_id' => $folder->id,
        ]);

        $this->actingAs($user)
            ->post("/files/{$folder->id}/copy", ['target_id' => null])
            ->assertRedirect();

        $copyFolder = File::where('name', 'src (copy)')->where('is_dir', true)->first();
        $this->assertNotNull($copyFolder);
        $this->assertDatabaseHas('files', ['parent_id' => $copyFolder->id, 'name' => 'c.txt']);
    }

    public function test_cannot_operate_on_another_users_file(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $file = File::factory()->for($owner, 'owner')->create();

        $this->actingAs($other)
            ->patch("/files/{$file->id}/rename", ['name' => 'hacked.txt'])
            ->assertForbidden();
    }
}
