<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileTreeTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_returns_owned_top_level_folders_and_files(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $folder = File::factory()->for($user, 'owner')->folder()->create(['name' => 'Docs']);
        File::factory()->for($user, 'owner')->folder()->create(['name' => 'Sub', 'parent_id' => $folder->id]);
        File::factory()->for($user, 'owner')->create(['name' => 'a.md', 'parent_id' => null, 'status' => File::STATUS_READY]);

        $res = $this->actingAs($user)->getJson('/files/tree')->assertOk()->json();

        $docs = collect($res['folders'])->firstWhere('name', 'Docs');
        $this->assertSame('Docs', $docs['name']);
        $this->assertTrue($docs['has_children']);
        $this->assertSame('a.md', collect($res['files'])->firstWhere('name', 'a.md')['name']);
    }

    public function test_parent_returns_that_folders_children(): void
    {
        $user = User::factory()->create();
        $folder = File::factory()->for($user, 'owner')->folder()->create(['name' => 'Docs']);
        File::factory()->for($user, 'owner')->create(['name' => 'inside.md', 'parent_id' => $folder->id]);

        $res = $this->actingAs($user)->getJson("/files/tree?parent={$folder->id}")->assertOk()->json();
        $this->assertSame('inside.md', collect($res['files'])->firstWhere('name', 'inside.md')['name']);
    }

    public function test_cannot_read_another_users_folder(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $folder = File::factory()->for($other, 'owner')->folder()->create();

        $this->actingAs($user)->getJson("/files/tree?parent={$folder->id}")->assertNotFound();
    }
}
