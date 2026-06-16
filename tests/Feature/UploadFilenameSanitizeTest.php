<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadFilenameSanitizeTest extends TestCase
{
    use RefreshDatabase;

    public function test_dangerous_filename_is_sanitized_on_upload(): void
    {
        Storage::fake('public');
        Queue::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('files.upload'), [
            'files' => [UploadedFile::fake()->create("ev\"il;\n\trm -rf.txt", 5, 'text/plain')],
        ])->assertRedirect();

        $stored = File::where('owner_id', $user->id)->value('name');

        $this->assertDoesNotMatchRegularExpression('/["\r\n\t;\/\\\\]/', $stored);
        $this->assertMatchesRegularExpression('/^[\w\-. ()]+$/u', $stored);
        $this->assertStringEndsWith('.txt', $stored);
    }
}
