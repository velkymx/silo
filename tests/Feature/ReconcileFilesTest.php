<?php

namespace Tests\Feature;

use App\Jobs\ProcessUploadedFile;
use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReconcileFilesTest extends TestCase
{
    use RefreshDatabase;

    public function test_redispatches_only_long_stuck_pending_files(): void
    {
        Queue::fake();
        $user = User::factory()->create();

        $stuck = File::factory()->for($user, 'owner')->create(['status' => File::STATUS_PENDING]);
        $stuck->forceFill(['updated_at' => now()->subHour()])->saveQuietly();

        // Recent pending — not yet stuck.
        File::factory()->for($user, 'owner')->create(['status' => File::STATUS_PENDING]);
        // Already done.
        File::factory()->for($user, 'owner')->create(['status' => File::STATUS_READY]);

        $this->artisan('files:reconcile')->assertSuccessful();

        Queue::assertPushed(ProcessUploadedFile::class, 1);
        Queue::assertPushed(fn (ProcessUploadedFile $job) => $job->fileId === $stuck->id);
    }
}
