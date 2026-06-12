<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\Group;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FilePermissionInheritanceTest extends TestCase
{
    use RefreshDatabase;

    private function storedFileIn(User $owner, ?int $parentId, string $name = 'doc.txt'): File
    {
        Storage::disk('public')->put($path = 'uploads/'.$owner->id.'/'.uniqid(), 'data');

        return File::factory()->for($owner, 'owner')->create([
            'name' => $name,
            'path' => $path,
            'parent_id' => $parentId,
        ]);
    }

    private function grant(File $folder, string $type, int $subjectId, string $ability): void
    {
        Permission::create([
            'file_id' => $folder->id,
            'subject_type' => $type,
            'subject_id' => $subjectId,
            'ability' => $ability,
        ]);
    }

    public function test_folder_read_grant_cascades_to_child_file(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $grantee = User::factory()->create();
        $folder = File::factory()->for($owner, 'owner')->folder()->create();
        $file = $this->storedFileIn($owner, $folder->id);

        $this->grant($folder, Permission::SUBJECT_USER, $grantee->id, Permission::ABILITY_READ);

        $this->actingAs($grantee)->get(route('files.download', $file))->assertOk();
    }

    public function test_grant_cascades_through_nested_folders(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $grantee = User::factory()->create();
        $top = File::factory()->for($owner, 'owner')->folder()->create();
        $mid = File::factory()->for($owner, 'owner')->folder()->create(['parent_id' => $top->id]);
        $file = $this->storedFileIn($owner, $mid->id);

        $this->grant($top, Permission::SUBJECT_USER, $grantee->id, Permission::ABILITY_READ);

        $this->actingAs($grantee)->get(route('files.download', $file))->assertOk();
    }

    public function test_inherited_read_does_not_imply_delete(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $grantee = User::factory()->create();
        $folder = File::factory()->for($owner, 'owner')->folder()->create();
        $file = $this->storedFileIn($owner, $folder->id);

        $this->grant($folder, Permission::SUBJECT_USER, $grantee->id, Permission::ABILITY_READ);

        $this->actingAs($grantee)->delete(route('files.delete', $file))->assertForbidden();
    }

    public function test_grant_does_not_leak_to_a_sibling_subtree(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $grantee = User::factory()->create();
        $shared = File::factory()->for($owner, 'owner')->folder()->create();
        $other = File::factory()->for($owner, 'owner')->folder()->create();
        $file = $this->storedFileIn($owner, $other->id);

        $this->grant($shared, Permission::SUBJECT_USER, $grantee->id, Permission::ABILITY_READ);

        $this->actingAs($grantee)->get(route('files.download', $file))->assertForbidden();
    }

    public function test_moving_a_file_into_a_shared_folder_grants_access(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $grantee = User::factory()->create();
        $folder = File::factory()->for($owner, 'owner')->folder()->create();
        $file = $this->storedFileIn($owner, null);
        $this->grant($folder, Permission::SUBJECT_USER, $grantee->id, Permission::ABILITY_READ);

        $this->actingAs($grantee)->get(route('files.download', $file))->assertForbidden();

        $file->update(['parent_id' => $folder->id]);

        $this->actingAs($grantee)->get(route('files.download', $file))->assertOk();
    }

    public function test_group_folder_grant_cascades(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $group = Group::create(['name' => 'team']);
        $member = User::factory()->create(['group_id' => $group->id]);
        $folder = File::factory()->for($owner, 'owner')->folder()->create();
        $file = $this->storedFileIn($owner, $folder->id);

        $this->grant($folder, Permission::SUBJECT_GROUP, $group->id, Permission::ABILITY_READ);

        $this->actingAs($member)->get(route('files.download', $file))->assertOk();
    }
}
