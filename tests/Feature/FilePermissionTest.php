<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\Group;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FilePermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_explicit_user_read_grant_allows_download(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        Storage::disk('public')->put('uploads/'.$owner->id.'/a.txt', 'hi');
        $file = File::factory()->for($owner, 'owner')->create([
            'name' => 'a.txt',
            'path' => 'uploads/'.$owner->id.'/a.txt',
        ]);

        Permission::factory()->forUser($other)->ability(Permission::ABILITY_READ)->create([
            'file_id' => $file->id,
        ]);

        $this->actingAs($other)
            ->get(route('files.download', $file))
            ->assertOk();
    }

    public function test_read_grant_does_not_imply_delete(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $file = File::factory()->for($owner, 'owner')->create();

        Permission::factory()->forUser($other)->ability(Permission::ABILITY_READ)->create([
            'file_id' => $file->id,
        ]);

        $this->actingAs($other)
            ->delete(route('files.delete', $file))
            ->assertForbidden();
    }

    public function test_group_grant_allows_members_to_delete(): void
    {
        Storage::fake('public');
        $group = Group::create(['name' => 'team']);
        $owner = User::factory()->create();
        $member = User::factory()->create(['group_id' => $group->id]);
        Storage::disk('public')->put('uploads/'.$owner->id.'/a.txt', 'hi');
        $file = File::factory()->for($owner, 'owner')->create([
            'path' => 'uploads/'.$owner->id.'/a.txt',
        ]);

        Permission::factory()->forGroup($group->id)->ability(Permission::ABILITY_DELETE)->create([
            'file_id' => $file->id,
        ]);

        $this->actingAs($member)
            ->delete(route('files.delete', $file))
            ->assertRedirect();

        $this->assertSoftDeleted($file);
    }

    public function test_admin_bypasses_permission_checks(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        Storage::disk('public')->put('uploads/'.$owner->id.'/a.txt', 'hi');
        $file = File::factory()->for($owner, 'owner')->create([
            'path' => 'uploads/'.$owner->id.'/a.txt',
        ]);

        $this->actingAs($admin)
            ->get(route('files.download', $file))
            ->assertOk();
    }

    public function test_user_without_grant_is_forbidden(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $file = File::factory()->for($owner, 'owner')->create();

        $this->actingAs($stranger)
            ->get(route('files.download', $file))
            ->assertForbidden();
    }
}
