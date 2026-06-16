<?php

namespace Tests\Feature;

use App\Jobs\RunBackup;
use App\Models\Backup;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CoverageGapsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_a_users_group(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $member = User::factory()->create();
        $group = Group::create(['name' => 'Staff']);

        $this->actingAs($admin)
            ->post(route('admin.users.updateGroup', $member), ['group_id' => $group->id])
            ->assertRedirect();

        $this->assertSame($group->id, $member->fresh()->group_id);
    }

    public function test_non_admin_cannot_update_a_users_group(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $other = User::factory()->create();
        $group = Group::create(['name' => 'Staff']);

        $this->actingAs($user)
            ->post(route('admin.users.updateGroup', $other), ['group_id' => $group->id])
            ->assertForbidden();
    }

    public function test_run_backup_job_produces_a_ready_backup(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $user = User::factory()->create(['is_admin' => true]);

        RunBackup::dispatchSync($user->id);

        $this->assertDatabaseHas('backups', ['status' => Backup::STATUS_READY, 'created_by' => $user->id]);
    }
}
