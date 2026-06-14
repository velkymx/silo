<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UploadTrashedCollisionTest extends TestCase
{
    use RefreshDatabase;

    // C2: a name sitting in the trash must not block (500) re-uploading the
    // same name; the new file is live, the trashed row is untouched.
    public function test_reupload_of_a_trashed_name_creates_a_new_live_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $folder = File::factory()->for($user, 'owner')->folder()->create(['name' => 'Docs', 'parent_id' => null]);

        $trashed = File::factory()->for($user, 'owner')->create(['name' => 'foo.txt', 'parent_id' => $folder->id]);
        $trashed->delete(); // to trash

        $this->actingAs($user)->post(route('files.upload'), [
            'files' => [UploadedFile::fake()->create('foo.txt', 8)],
            'parent_id' => $folder->id,
        ])->assertRedirect();

        // A live foo.txt now exists in the folder, distinct from the trashed one.
        $this->assertDatabaseHas('files', ['name' => 'foo.txt', 'parent_id' => $folder->id, 'deleted_at' => null]);
        $this->assertSoftDeleted('files', ['id' => $trashed->id]);
        $this->assertSame(2, File::withTrashed()->where('name', 'foo.txt')->where('parent_id', $folder->id)->count());
    }
}
