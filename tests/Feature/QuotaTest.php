<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\User;
use App\Services\QuotaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class QuotaTest extends TestCase
{
    use RefreshDatabase;

    public function test_used_bytes_counts_files_and_versions(): void
    {
        $user = User::factory()->create();
        File::factory()->for($user, 'owner')->create(['size' => 1000]);
        $f = File::factory()->for($user, 'owner')->create(['size' => 500]);
        \App\Models\FileVersion::factory()->create(['file_id' => $f->id, 'size' => 250]);
        File::factory()->for($user, 'owner')->folder()->create(); // folders don't count

        $this->assertSame(1750, app(QuotaService::class)->usedBytes($user->id));
    }

    public function test_upload_is_blocked_when_over_quota(): void
    {
        Storage::fake('public');
        config(['filemanager.user_quota_mb' => 1, 'filemanager.max_upload_kb' => 5120]);
        $user = User::factory()->create();
        // Already using ~1 MB.
        File::factory()->for($user, 'owner')->create(['size' => 1024 * 1024]);

        $this->actingAs($user)->post(route('files.upload'), [
            'files' => [UploadedFile::fake()->create('big.bin', 200)], // 200 KB pushes over 1 MB
        ])->assertSessionHasErrors('files');

        $this->assertSame(1, File::where('owner_id', $user->id)->where('is_dir', false)->count());
    }

    public function test_upload_allowed_under_quota_and_usage_reported(): void
    {
        Storage::fake('public');
        config(['filemanager.user_quota_mb' => 10]);
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('files.upload'), [
            'files' => [UploadedFile::fake()->create('ok.bin', 100)],
        ])->assertRedirect();

        $this->actingAs($user)->get('/')->assertInertia(
            fn (Assert $p) => $p->where('storage.quota', 10 * 1024 * 1024)->where('storage.used', fn ($u) => $u > 0)
        );
    }

    public function test_used_bytes_includes_trashed_files(): void
    {
        $user = User::factory()->create();
        $f = File::factory()->for($user, 'owner')->create(['size' => 800]);
        $f->delete(); // soft-deleted — still counts toward quota

        $this->assertSame(800, app(QuotaService::class)->usedBytes($user->id));
    }

    public function test_unlimited_quota_never_blocks(): void
    {
        config(['filemanager.user_quota_mb' => 0]);
        $user = User::factory()->create();

        $this->assertFalse(app(QuotaService::class)->wouldExceed($user->id, PHP_INT_MAX));
    }
}
