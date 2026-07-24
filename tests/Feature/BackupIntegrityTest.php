<?php

namespace Tests\Feature;

use App\Models\Backup;
use App\Models\File;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupIntegrityTest extends TestCase
{
    use RefreshDatabase;

    private function makeBackup(): Backup
    {
        Storage::fake('public');
        Storage::fake('local');
        $user = User::factory()->create();
        Storage::disk('public')->put('uploads/1/a.txt', 'IMPORTANT');
        File::factory()->for($user, 'owner')->create(['name' => 'a.txt', 'path' => 'uploads/1/a.txt', 'disk' => 'public']);

        return app(BackupService::class)->create($user->id);
    }

    public function test_create_records_a_sha256_checksum(): void
    {
        $backup = $this->makeBackup();

        $this->assertNotNull($backup->checksum);
        $this->assertSame(64, strlen($backup->checksum));
        $expected = hash_file('sha256', Storage::disk($backup->disk)->path($backup->path));
        $this->assertSame($expected, $backup->checksum);
    }

    public function test_restore_rejects_a_tampered_archive(): void
    {
        $backup = $this->makeBackup();

        // Corrupt the archive after it was recorded (bit-rot / truncated upload).
        Storage::disk($backup->disk)->put($backup->path, 'not a real zip');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('integrity check failed');
        app(BackupService::class)->restore($backup->fresh());
    }

    public function test_restore_rejects_a_backup_without_a_checksum(): void
    {
        $backup = $this->makeBackup();
        $backup->update(['checksum' => null]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('no integrity checksum');
        app(BackupService::class)->restore($backup->fresh());
    }

    public function test_verify_integrity_passes_for_an_untouched_archive(): void
    {
        $backup = $this->makeBackup();

        app(BackupService::class)->verifyIntegrity($backup->fresh());

        // No exception thrown means the archive matches its recorded checksum.
        $this->assertTrue(true);
    }
}
