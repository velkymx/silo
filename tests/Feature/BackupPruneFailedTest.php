<?php

namespace Tests\Feature;

use App\Models\Backup;
use App\Models\Setting;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackupPruneFailedTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_failed_backups_are_pruned(): void
    {
        Setting::put("backup.retention", 7);
        $user = User::factory()->create();

        $old = Backup::create(['filename' => 'a.zip', 'status' => Backup::STATUS_FAILED, 'created_by' => $user->id]);
        $old->forceFill(['created_at' => now()->subDays(40)])->save();

        $recent = Backup::create(['filename' => 'b.zip', 'status' => Backup::STATUS_FAILED, 'created_by' => $user->id]);

        app(BackupService::class)->prune();

        $this->assertDatabaseMissing('backups', ['id' => $old->id]);
        $this->assertDatabaseHas('backups', ['id' => $recent->id]);
    }
}
