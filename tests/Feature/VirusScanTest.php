<?php

namespace Tests\Feature;

use App\Jobs\ProcessUploadedFile;
use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VirusScanTest extends TestCase
{
    use RefreshDatabase;

    private function uploadedFile(User $user): File
    {
        Storage::fake('public');
        Storage::disk('public')->put($path = 'uploads/'.$user->id.'/doc.bin', 'payload');

        return File::factory()->for($user, 'owner')->create([
            'name' => 'doc.bin', 'path' => $path, 'disk' => 'public',
            'status' => File::STATUS_PENDING,
        ]);
    }

    public function test_infected_upload_is_quarantined_and_blob_removed(): void
    {
        config(['filemanager.av.enabled' => true, 'filemanager.av.command' => 'false']); // exit code 1
        $user = User::factory()->create();
        $file = $this->uploadedFile($user);

        ProcessUploadedFile::dispatchSync($file->id);

        $file->refresh();
        $this->assertSame(File::STATUS_INFECTED, $file->status);
        Storage::disk('public')->assertMissing($file->path);

        // Serving an infected file is blocked.
        $this->actingAs($user)->get(route('files.raw', $file))->assertNotFound();
        $this->actingAs($user)->get(route('files.download', $file))->assertNotFound();
    }

    public function test_clean_upload_passes_when_scanning_disabled(): void
    {
        config(['filemanager.av.enabled' => false]);
        $user = User::factory()->create();
        $file = $this->uploadedFile($user);

        ProcessUploadedFile::dispatchSync($file->id);

        $file->refresh();
        $this->assertSame(File::STATUS_READY, $file->status);
        Storage::disk('public')->assertExists($file->path);
    }
}
