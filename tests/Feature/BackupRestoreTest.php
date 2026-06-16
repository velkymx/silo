<?php

namespace Tests\Feature;

use App\Models\Backup;
use App\Models\File;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupRestoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_backup_then_wipe_then_restore_recovers_data(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $user = User::factory()->create(['email' => 'keep@x.test']);
        Storage::disk('public')->put('uploads/1/report.txt', 'IMPORTANT CONTENTS');
        $file = File::factory()->for($user, 'owner')->create([
            'name' => 'report.txt', 'path' => 'uploads/1/report.txt', 'disk' => 'public',
        ]);

        // 1. Back up.
        $backup = app(BackupService::class)->create($user->id);
        $this->assertSame(Backup::STATUS_READY, $backup->status);

        // 2. Wipe: drop the row + the blob.
        File::where('id', $file->id)->forceDelete();
        Storage::disk('public')->delete('uploads/1/report.txt');
        $this->assertDatabaseMissing('files', ['id' => $file->id]);
        Storage::disk('public')->assertMissing('uploads/1/report.txt');

        // 3. Restore.
        $summary = app(BackupService::class)->restore($backup->fresh());

        // 4. Everything is back, byte-for-byte.
        $this->assertDatabaseHas('files', ['name' => 'report.txt', 'owner_id' => $user->id]);
        $this->assertDatabaseHas('users', ['email' => 'keep@x.test']);
        $this->assertSame('IMPORTANT CONTENTS', Storage::disk('public')->get('uploads/1/report.txt'));
        $this->assertGreaterThanOrEqual(1, collect($summary['disks'])->sum());
    }

    public function test_concurrent_restore_is_rejected(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $user = User::factory()->create();
        Storage::disk('public')->put('uploads/1/a.txt', 'x');
        File::factory()->for($user, 'owner')->create(['name' => 'a.txt', 'path' => 'uploads/1/a.txt', 'disk' => 'public']);
        $backup = app(BackupService::class)->create($user->id);

        // Simulate another restore already holding the single-process lock.
        $lock = Cache::lock('backup-restore', 600);
        $this->assertTrue($lock->get());

        try {
            $this->expectException(\RuntimeException::class);
            app(BackupService::class)->restore($backup->fresh());
        } finally {
            $lock->release();
        }
    }

    public function test_restore_command_requires_ready_backup(): void
    {
        $this->artisan('backup:restore', ['id' => 99999, '--force' => true])->assertFailed();
    }
}
