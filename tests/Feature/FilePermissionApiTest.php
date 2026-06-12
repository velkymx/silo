<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\Group;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilePermissionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_grant_abilities_to_a_user_by_email(): void
    {
        $owner = User::factory()->create();
        $grantee = User::factory()->create(['email' => 'pal@example.com']);
        $file = File::factory()->for($owner, 'owner')->create();

        $this->actingAs($owner)->postJson(route('files.permissions.store', $file), [
            'subject_type' => 'user',
            'email' => 'pal@example.com',
            'abilities' => ['read', 'write'],
        ])->assertOk();

        $this->assertDatabaseHas('permissions', [
            'file_id' => $file->id,
            'subject_type' => 'user',
            'subject_id' => $grantee->id,
            'ability' => 'read',
        ]);
        $this->assertSame(2, Permission::where('file_id', $file->id)->count());
    }

    public function test_owner_can_grant_to_a_group(): void
    {
        $owner = User::factory()->create();
        $group = Group::create(['name' => 'team']);
        $file = File::factory()->for($owner, 'owner')->create();

        $this->actingAs($owner)->postJson(route('files.permissions.store', $file), [
            'subject_type' => 'group',
            'group_id' => $group->id,
            'abilities' => ['read'],
        ])->assertOk();

        $this->assertDatabaseHas('permissions', [
            'file_id' => $file->id,
            'subject_type' => 'group',
            'subject_id' => $group->id,
            'ability' => 'read',
        ]);
    }

    public function test_unknown_email_is_rejected(): void
    {
        $owner = User::factory()->create();
        $file = File::factory()->for($owner, 'owner')->create();

        $this->actingAs($owner)->postJson(route('files.permissions.store', $file), [
            'subject_type' => 'user',
            'email' => 'nobody@example.com',
            'abilities' => ['read'],
        ])->assertStatus(422);
    }

    public function test_a_user_with_share_grant_can_manage_permissions(): void
    {
        $owner = User::factory()->create();
        $sharer = User::factory()->create();
        $file = File::factory()->for($owner, 'owner')->create();
        Permission::create([
            'file_id' => $file->id, 'subject_type' => 'user',
            'subject_id' => $sharer->id, 'ability' => 'share',
        ]);

        $this->actingAs($sharer)->getJson(route('files.permissions.index', $file))->assertOk();
    }

    public function test_user_without_share_cannot_view_permissions(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $file = File::factory()->for($owner, 'owner')->create();

        $this->actingAs($other)->getJson(route('files.permissions.index', $file))->assertForbidden();
    }

    public function test_owner_can_revoke_a_grant(): void
    {
        $owner = User::factory()->create();
        $file = File::factory()->for($owner, 'owner')->create();
        $perm = Permission::create([
            'file_id' => $file->id, 'subject_type' => 'group',
            'subject_id' => 1, 'ability' => 'read',
        ]);

        $this->actingAs($owner)
            ->deleteJson(route('files.permissions.destroy', [$file, $perm]))
            ->assertOk();

        $this->assertDatabaseMissing('permissions', ['id' => $perm->id]);
    }
}
