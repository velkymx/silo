<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PurgeFailedBlobsTest extends TestCase
{
    use RefreshDatabase;

    public function test_old_failed_files_and_blobs_are_purged(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Storage::disk('public')->put($path = 'uploads/'.$user->id.'/bad.bin', 'x');

        $file = File::factory()->for($user, 'owner')->create([
            'name' => 'bad.bin', 'path' => $path, 'disk' => 'public', 'status' => File::STATUS_FAILED,
        ]);
        $file->forceFill(['updated_at' => now()->subDays(30)])->save();

        $this->artisan('failed_blobs:purge', ['--days' => 7])->assertSuccessful();

        $this->assertDatabaseMissing('files', ['id' => $file->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_recent_failed_files_are_kept(): void
    {
        $user = User::factory()->create();
        $file = File::factory()->for($user, 'owner')->create(['status' => File::STATUS_FAILED]);

        $this->artisan('failed_blobs:purge', ['--days' => 7])->assertSuccessful();

        $this->assertDatabaseHas('files', ['id' => $file->id]);
    }
}
