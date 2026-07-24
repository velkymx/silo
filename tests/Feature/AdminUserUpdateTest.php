<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_user_with_null_group(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['group_id' => Group::create(['name' => 'Original'])->id]);

        $response = $this->actingAs($admin)
            ->patch(route('admin.users.update', $user), [
                'name' => 'Updated Name',
                'email' => $user->email,
                'group_id' => null,
                'is_admin' => false,
                'password' => '',
                'password_confirmation' => '',
            ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'group_id' => null,
        ]);
    }

    public function test_admin_can_update_user_with_a_valid_group(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create();
        $group = Group::create(['name' => 'Engineering']);

        $response = $this->actingAs($admin)
            ->patch(route('admin.users.update', $user), [
                'name' => $user->name,
                'email' => $user->email,
                'group_id' => $group->id,
                'is_admin' => false,
                'password' => '',
                'password_confirmation' => '',
            ]);

        $response->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'group_id' => $group->id,
        ]);
    }

    public function test_non_admin_cannot_update_user(): void
    {
        $actor = User::factory()->create(['is_admin' => false]);
        $user = User::factory()->create();

        $response = $this->actingAs($actor)
            ->patch(route('admin.users.update', $user), [
                'name' => 'Hacked Name',
                'email' => $user->email,
                'group_id' => null,
            ]);

        $response->assertForbidden();
        $this->assertDatabaseMissing('users', ['id' => $user->id, 'name' => 'Hacked Name']);
    }
}
