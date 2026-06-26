<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_is_rejected_from_users_route(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user)->get('/users')->assertForbidden();
    }

    public function test_non_admin_is_rejected_from_groups_route(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user)->get('/groups')->assertForbidden();
    }

    public function test_non_admin_is_rejected_from_audit_route(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user)->get('/audit')->assertForbidden();
    }

    public function test_admin_can_access_users_route(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $this->actingAs($admin)->get('/users')->assertOk();
    }
}
