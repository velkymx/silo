<?php

namespace Tests\Feature;

use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSelfDemoteTest extends TestCase
{
    use RefreshDatabase;

    private function payload(User $user, array $overrides = []): array
    {
        return array_merge([
            'name' => $user->name,
            'email' => $user->email,
            'group_id' => Group::create(['name' => 'G'.uniqid()])->id,
            'is_admin' => false,
        ], $overrides);
    }

    public function test_last_admin_cannot_remove_own_admin_flag(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->patchJson(route('admin.users.update', $admin), $this->payload($admin))
            ->assertStatus(422);

        $this->assertTrue((bool) $admin->fresh()->is_admin);
    }

    public function test_demotion_allowed_when_another_admin_exists(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $other = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->patch(route('admin.users.update', $other), $this->payload($other))
            ->assertRedirect();

        $this->assertFalse((bool) $other->fresh()->is_admin);
    }
}
