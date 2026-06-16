<?php

namespace Tests\Feature;

use App\Models\Backup;
use App\Models\Setting;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_service_creates_a_ready_compressed_archive(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('uploads/1/a.txt', 'hello');

        $backup = app(BackupService::class)->create();

        $this->assertSame(Backup::STATUS_READY, $backup->status);
        $this->assertContains($backup->compression, ['bzip2', 'deflate']);
        $this->assertTrue(Storage::disk(BackupService::DISK)->exists($backup->path));
        $this->assertGreaterThan(0, $backup->size);
    }

    public function test_non_admin_cannot_reach_backups(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $this->actingAs($user)->get('/backups')->assertForbidden();
        $this->actingAs($user)->post('/backups')->assertForbidden();
    }

    public function test_admin_can_run_and_download_a_backup(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        // Run now (QUEUE sync in tests → builds immediately).
        $this->actingAs($admin)->post('/backups')->assertRedirect();

        $backup = Backup::firstOrFail();
        $this->assertSame(Backup::STATUS_READY, $backup->status);

        $this->actingAs($admin)->get("/backups/{$backup->id}/download")
            ->assertOk()
            ->assertHeader('content-disposition', 'attachment; filename='.$backup->filename);
    }

    public function test_schedule_settings_persist(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put('/backups/schedule', [
            'frequency' => 'weekly', 'retention' => 14,
        ])->assertRedirect();

        $this->assertSame('weekly', Setting::get('backup.frequency'));
        $this->assertSame(14, (int) Setting::get('backup.retention'));
    }

    public function test_retention_prunes_old_backups(): void
    {
        Storage::fake('public');
        Setting::put('backup.retention', 2);
        $service = app(BackupService::class);

        $service->create();
        $service->create();
        $service->create();

        $this->assertSame(2, Backup::where('status', Backup::STATUS_READY)->count());
    }
}
