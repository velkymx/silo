<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\FileVersion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CopyVersionResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_copy_of_a_versioned_file_starts_at_v1_with_no_history(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        Storage::disk('public')->put($path = 'uploads/'.$user->id.'/doc.txt', 'v5 contents');

        $file = File::factory()->for($user, 'owner')->create([
            'name' => 'doc.txt', 'path' => $path, 'disk' => 'public', 'version' => 5,
        ]);
        $dest = File::factory()->for($user, 'owner')->folder()->create();

        // Source has version history.
        FileVersion::create([
            'file_id' => $file->id, 'version' => 4, 'name' => 'doc.txt',
            'path' => $path, 'disk' => 'public', 'size' => 5, 'hash' => 'x', 'created_by' => $user->id,
        ]);

        $this->actingAs($user)
            ->post("/files/{$file->id}/copy", ['target_id' => $dest->id])
            ->assertRedirect();

        $copy = File::where('parent_id', $dest->id)->where('name', 'doc.txt')->firstOrFail();
        $this->assertSame(1, $copy->version);
        $this->assertSame(0, FileVersion::where('file_id', $copy->id)->count());
    }
}
