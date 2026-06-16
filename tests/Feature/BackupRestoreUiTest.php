<?php

namespace Tests\Feature;

use App\Jobs\RestoreBackup;
use App\Models\Backup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BackupRestoreUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_restore_dispatches_job_for_ready_backup(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['is_admin' => true]);
        $backup = Backup::create(['filename' => 'b.zip', 'path' => 'backups/b.zip', 'status' => Backup::STATUS_READY]);

        $this->actingAs($admin)->post("/backups/{$backup->id}/restore")->assertRedirect();
        Queue::assertPushed(fn (RestoreBackup $j) => $j->backupId === $backup->id);
    }

    public function test_non_admin_cannot_restore(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $backup = Backup::create(['filename' => 'b.zip', 'status' => Backup::STATUS_READY]);
        $this->actingAs($user)->post("/backups/{$backup->id}/restore")->assertForbidden();
    }
}
