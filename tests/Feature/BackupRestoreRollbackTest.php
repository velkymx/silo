<?php

namespace Tests\Feature;

use App\Models\Backup;
use App\Models\File;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * A BackupService whose blob restore fails the first time it runs (the real
 * restore) but succeeds afterwards (the rollback from the pre-restore
 * snapshot). Lets us prove a mid-restore failure leaves the system in its
 * pre-restore state rather than half-restored.
 */
class FailingRestoreService extends BackupService
{
    public int $blobCalls = 0;

    protected function restoreBlobs(string $work): array
    {
        $this->blobCalls++;
        if ($this->blobCalls === 1) {
            throw new \RuntimeException('simulated blob failure mid-restore');
        }

        return parent::restoreBlobs($work);
    }
}

class BackupRestoreRollbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_failed_restore_rolls_back_to_the_pre_restore_state(): void
    {
        Storage::fake('public');
        Storage::fake('local');

        $user = User::factory()->create(['email' => 'live@x.test']);
        Storage::disk('public')->put('uploads/1/doc.txt', 'ORIGINAL');
        $file = File::factory()->for($user, 'owner')->create([
            'name' => 'original.txt', 'path' => 'uploads/1/doc.txt', 'disk' => 'public',
        ]);

        // Snapshot the ORIGINAL state.
        $backup = app(BackupService::class)->create($user->id);
        $this->assertSame(Backup::STATUS_READY, $backup->status);

        // Mutate the live state to something clearly different.
        $file->update(['name' => 'modified.txt']);
        Storage::disk('public')->put('uploads/1/doc.txt', 'MODIFIED');

        // Restoring the ORIGINAL backup fails mid-way; it must roll the system
        // back to the current (MODIFIED) state, not leave it half-restored.
        $service = new FailingRestoreService();
        try {
            $service->restore($backup->fresh());
            $this->fail('Expected the failing restore to throw.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('rolled back', $e->getMessage());
        }

        // The blob restore ran twice: once for the (failed) real restore, once
        // for the successful rollback.
        $this->assertSame(2, $service->blobCalls);

        // Live state is intact: the MODIFIED data survived, ORIGINAL did not
        // clobber it.
        $this->assertDatabaseHas('files', ['id' => $file->id, 'name' => 'modified.txt']);
        $this->assertDatabaseMissing('files', ['name' => 'original.txt']);
        $this->assertSame('MODIFIED', Storage::disk('public')->get('uploads/1/doc.txt'));
    }
}
