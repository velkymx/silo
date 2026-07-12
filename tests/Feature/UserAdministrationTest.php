<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\QuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_user_cannot_log_in(): void
    {
        $user = User::factory()->create(['disabled_at' => now()]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_live_session_is_terminated_when_disabled(): void
    {
        $user = $this->asUser();

        $this->get('/dashboard')->assertOk();

        $user->update(['disabled_at' => now()]);

        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_admin_disables_a_user_from_the_edit_form(): void
    {
        $this->asAdmin();
        $target = User::factory()->create();

        $this->patch("/admin/users/{$target->id}", [
            'name' => $target->name, 'email' => $target->email,
            'group_id' => null, 'is_admin' => false, 'disabled' => true,
        ])->assertRedirect();

        $this->assertNotNull($target->fresh()->disabled_at);
    }

    public function test_admin_cannot_disable_their_own_account(): void
    {
        $admin = $this->asAdmin();

        $this->patch("/admin/users/{$admin->id}", [
            'name' => $admin->name, 'email' => $admin->email,
            'group_id' => null, 'is_admin' => true, 'disabled' => true,
        ])->assertSessionHasErrors('disabled');

        $this->assertNull($admin->fresh()->disabled_at);
    }

    public function test_last_active_admin_cannot_be_disabled(): void
    {
        // One disabled admin exists, so $admin is the last ACTIVE administrator;
        // disabling them (even ignoring the self rule) must be refused.
        $admin = $this->asAdmin();
        User::factory()->create(['is_admin' => true, 'disabled_at' => now()]);

        $this->patch("/admin/users/{$admin->id}", [
            'name' => $admin->name, 'email' => $admin->email,
            'group_id' => null, 'is_admin' => true, 'disabled' => true,
        ])->assertSessionHasErrors('disabled');

        $this->assertNull($admin->fresh()->disabled_at);
    }

    public function test_quota_override_beats_the_global_default(): void
    {
        config(['filemanager.user_quota_mb' => 100]);
        $user = User::factory()->create(['quota_mb' => 5]);
        $unlimited = User::factory()->create(['quota_mb' => 0]);
        $default = User::factory()->create();

        $quota = app(QuotaService::class);

        $this->assertSame(5 * 1024 * 1024, $quota->quotaBytes($user->id));
        $this->assertSame(0, $quota->quotaBytes($unlimited->id));
        $this->assertSame(100 * 1024 * 1024, $quota->quotaBytes($default->id));

        // wouldExceed respects the override: 6MB into a 5MB quota fails …
        $this->assertTrue($quota->wouldExceed($user->id, 6 * 1024 * 1024));
        // … but the unlimited user takes anything.
        $this->assertFalse($quota->wouldExceed($unlimited->id, 500 * 1024 * 1024));
    }

    public function test_admin_sets_a_quota_from_the_edit_form(): void
    {
        $this->asAdmin();
        $target = User::factory()->create();

        $this->patch("/admin/users/{$target->id}", [
            'name' => $target->name, 'email' => $target->email,
            'group_id' => null, 'is_admin' => false, 'disabled' => false,
            'quota_mb' => 2048,
        ])->assertRedirect();

        $this->assertSame(2048, $target->fresh()->quota_mb);
    }
}
