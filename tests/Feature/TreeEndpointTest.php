<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TreeEndpointTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_and_folder_children_folders_first(): void
    {
        $user = User::factory()->create();
        $folder = File::factory()->for($user, 'owner')->folder()->create(['name' => 'Docs', 'parent_id' => null]);
        File::factory()->for($user, 'owner')->create(['name' => 'root.txt', 'parent_id' => null]);
        File::factory()->for($user, 'owner')->folder()->create(['name' => 'Sub', 'parent_id' => $folder->id]);
        File::factory()->for($user, 'owner')->create(['name' => 'inside.txt', 'parent_id' => $folder->id]);

        $root = $this->actingAs($user)->getJson('/tree')->assertOk()->json();
        $this->assertSame('Docs', $root[0]['name']); // folder first
        $this->assertCount(2, $root);

        $kids = $this->actingAs($user)->getJson("/tree/{$folder->id}")->assertOk()->json();
        $this->assertSame(['Sub', 'inside.txt'], array_column($kids, 'name'));
    }

    public function test_has_children_flag_distinguishes_empty_folders(): void
    {
        $user = User::factory()->create();
        $parent = File::factory()->for($user, 'owner')->folder()->create(['name' => 'Parent', 'parent_id' => null]);
        $empty = File::factory()->for($user, 'owner')->folder()->create(['name' => 'Empty', 'parent_id' => null]);
        File::factory()->for($user, 'owner')->create(['name' => 'child.txt', 'parent_id' => $parent->id]);

        $root = collect($this->actingAs($user)->getJson('/tree')->assertOk()->json())
            ->keyBy('name');

        $this->assertTrue($root['Parent']['has_children']);
        $this->assertFalse($root['Empty']['has_children']);
    }

    public function test_cannot_read_another_users_folder(): void
    {
        $folder = File::factory()->for(User::factory()->create(), 'owner')->folder()->create();
        $this->actingAs(User::factory()->create())->getJson("/tree/{$folder->id}")->assertNotFound();
    }
}
