<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ServeReadyOnlyTest extends TestCase
{
    use RefreshDatabase;

    private function fileWithStatus(User $owner, string $status): File
    {
        Storage::disk('public')->put($path = 'uploads/'.$owner->id.'/f.txt', 'data');

        return File::factory()->for($owner, 'owner')->create([
            'name' => 'f.txt', 'path' => $path, 'disk' => 'public', 'status' => $status,
        ]);
    }

    public function test_ready_file_is_served(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $file = $this->fileWithStatus($owner, File::STATUS_READY);

        $this->actingAs($owner)->get(route('files.raw', $file))->assertOk();
        $this->actingAs($owner)->get(route('files.download', $file))->assertOk();
    }

    public function test_unscanned_or_failed_file_is_not_served(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();

        foreach ([File::STATUS_PENDING, File::STATUS_FAILED, File::STATUS_INFECTED] as $status) {
            $file = $this->fileWithStatus($owner, $status);
            $this->actingAs($owner)->get(route('files.raw', $file))->assertNotFound();
            $this->actingAs($owner)->get(route('files.download', $file))->assertNotFound();
        }
    }
}
