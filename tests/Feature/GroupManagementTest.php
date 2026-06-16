<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\Group;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GroupManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_update_and_delete_groups(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post('/groups', ['name' => 'Engineering'])->assertRedirect();
        $group = Group::where('name', 'Engineering')->firstOrFail();

        $this->actingAs($admin)->patch("/groups/{$group->id}", ['name' => 'Eng'])->assertRedirect();
        $this->assertSame('Eng', $group->fresh()->name);

        $this->actingAs($admin)->delete("/groups/{$group->id}")->assertRedirect();
        $this->assertDatabaseMissing('groups', ['id' => $group->id]);
    }

    public function test_group_index_lists_groups_with_member_counts(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $group = Group::create(['name' => 'Sales']);
        User::factory()->count(2)->create(['group_id' => $group->id]);

        $this->actingAs($admin)->get('/groups')->assertInertia(
            fn (Assert $p) => $p->component('Admin/Groups/Index')
                ->where('groups.0.name', 'Sales')
                ->where('groups.0.users_count', 2)
        );
    }

    public function test_non_admin_cannot_manage_groups(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get('/groups')->assertForbidden();
        $this->actingAs($user)->post('/groups', ['name' => 'X'])->assertForbidden();
    }

    public function test_folder_can_be_shared_and_tagged_via_the_same_endpoints(): void
    {
        $owner = User::factory()->create();
        $grantee = User::factory()->create(['email' => 'g@example.com']);
        $folder = File::factory()->for($owner, 'owner')->folder()->create();

        // Folder sharing uses the same permissions endpoint as files.
        $this->actingAs($owner)->postJson(route('files.permissions.store', $folder), [
            'subject_type' => 'user', 'email' => 'g@example.com', 'abilities' => ['read'],
        ])->assertOk();
        $this->assertDatabaseHas('permissions', [
            'file_id' => $folder->id, 'subject_id' => $grantee->id, 'ability' => 'read',
        ]);

        // Folder tagging uses the same tags endpoint as files.
        $this->actingAs($owner)->put(route('files.tags', $folder), ['tags' => ['projects']]);
        $this->assertCount(1, $folder->fresh()->tags);
    }

    public function test_tagged_folder_appears_in_tag_filter(): void
    {
        $user = User::factory()->create();
        $folder = File::factory()->for($user, 'owner')->folder()->create(['name' => 'Tagged Dir']);
        $this->actingAs($user)->put(route('files.tags', $folder), ['tags' => ['flagged']]);
        $tag = $user->tags ?? \App\Models\Tag::where('name', 'flagged')->firstOrFail();

        $this->actingAs($user)->get('/?tag='.$tag->id)->assertInertia(
            fn (Assert $p) => $p->has('folders', 1)->where('folders.0.name', 'Tagged Dir')
        );
    }
}
