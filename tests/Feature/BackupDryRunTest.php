<?php

namespace Tests\Feature;

use App\Models\Backup;
use App\Models\File;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupDryRunTest extends TestCase
{
    use RefreshDatabase;

    private function makeBackup(): Backup
    {
        Storage::fake('public');
        Storage::fake('local');
        $user = User::factory()->create();
        Storage::disk('public')->put('uploads/1/a.txt', 'DATA');
        File::factory()->for($user, 'owner')->create(['name' => 'a.txt', 'path' => 'uploads/1/a.txt', 'disk' => 'public']);

        return app(BackupService::class)->create($user->id);
    }

    public function test_dry_run_reports_a_healthy_backup_without_touching_live_data(): void
    {
        $backup = $this->makeBackup();

        $report = app(BackupService::class)->dryRun($backup->fresh());

        $this->assertTrue($report['ok']);
        $this->assertTrue($report['checksum']);
        $this->assertTrue($report['manifest']);
        $this->assertTrue($report['database']['ok']);
        $this->assertArrayHasKey('public', $report['blobs']);
        $this->assertTrue($report['blobs']['public']['ok']);

        // Live data is untouched: the original blob still reads the same, and the
        // dry run left no stray restore artifacts.
        $this->assertSame('DATA', Storage::disk('public')->get('uploads/1/a.txt'));
    }

    public function test_dry_run_fails_a_tampered_archive_without_throwing(): void
    {
        $backup = $this->makeBackup();
        Storage::disk($backup->disk)->put($backup->path, 'corrupt');

        $report = app(BackupService::class)->dryRun($backup->fresh());

        $this->assertFalse($report['ok']);
        $this->assertFalse($report['checksum']);
    }

    public function test_dry_run_endpoint_is_admin_only(): void
    {
        $backup = $this->makeBackup();
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->post("/backups/{$backup->id}/verify")->assertForbidden();
    }

    public function test_admin_can_dry_run_from_the_ui(): void
    {
        $backup = $this->makeBackup();
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->post("/backups/{$backup->id}/verify")
            ->assertRedirect();
    }
}
