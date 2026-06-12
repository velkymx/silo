<?php

namespace Tests\Feature;

use App\Jobs\ProcessUploadedFile;
use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProcessUploadedFileTest extends TestCase
{
    use RefreshDatabase;

    public function test_upload_marks_file_pending_and_dispatches_job(): void
    {
        Storage::fake('public');
        Queue::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('files.upload'), [
            'files' => [UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')],
        ])->assertRedirect();

        $file = File::where('owner_id', $user->id)->first();
        $this->assertSame(File::STATUS_PENDING, $file->status);
        Queue::assertPushed(ProcessUploadedFile::class, fn ($job) => $job->fileId === $file->id);
    }

    public function test_job_extracts_image_dimensions_and_marks_ready(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $image = UploadedFile::fake()->image('pic.png', 32, 18);
        $path = 'uploads/'.$user->id.'/pic.png';
        Storage::disk('public')->put($path, file_get_contents($image->getRealPath()));

        $file = File::factory()->for($user, 'owner')->create([
            'name' => 'pic.png',
            'path' => $path,
            'mime' => 'image/png',
            'status' => File::STATUS_PENDING,
        ]);

        ProcessUploadedFile::dispatchSync($file->id);

        $file->refresh();
        $this->assertSame(File::STATUS_READY, $file->status);
        $this->assertSame(32, $file->metadata['width']);
        $this->assertSame(18, $file->metadata['height']);
    }

    public function test_job_marks_failed_when_blob_missing(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $file = File::factory()->for($user, 'owner')->create([
            'path' => 'uploads/'.$user->id.'/gone.txt',
            'status' => File::STATUS_PENDING,
        ]);

        ProcessUploadedFile::dispatchSync($file->id);

        $this->assertSame(File::STATUS_FAILED, $file->fresh()->status);
    }
}
