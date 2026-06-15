<?php

namespace Tests\Feature;

use App\Models\Backup;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

class BackupRestoreTraversalTest extends TestCase
{
    use RefreshDatabase;

    public function test_restore_rejects_path_traversal_entries(): void
    {
        Storage::fake('local');
        $user = User::factory()->create();

        // Hand-craft a malicious archive with a traversal entry.
        $abs = Storage::disk('local')->path('backups/evil.zip');
        @mkdir(dirname($abs), 0775, true);
        $zip = new ZipArchive();
        $zip->open($abs, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('../../escape.txt', 'pwned');
        $zip->addFromString('database/database.json', '{}');
        $zip->close();

        $backup = Backup::create([
            'filename' => 'evil.zip',
            'path' => 'backups/evil.zip',
            'disk' => 'local',
            'status' => Backup::STATUS_READY,
            'created_by' => $user->id,
        ]);

        $this->expectException(\RuntimeException::class);
        app(BackupService::class)->restore($backup);
    }
}
