<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotoClusterTest extends TestCase
{
    use RefreshDatabase;

    private function image(User $owner): File
    {
        return File::factory()->for($owner, 'owner')->create(['name' => 'p.jpg', 'mime' => 'image/jpeg']);
    }

    public function test_set_cover_with_foreign_photo_returns_404(): void
    {
        $owner = User::factory()->create();
        $album = Album::create(['owner_id' => $owner->id, 'name' => 'Trip']);
        $foreign = $this->image(User::factory()->create());

        $this->actingAs($owner)
            ->post(route('photos.albums.cover', $album), ['file_ids' => [$foreign->id]])
            ->assertNotFound();
    }

    public function test_trashed_photo_cannot_be_added_to_album(): void
    {
        $owner = User::factory()->create();
        $album = Album::create(['owner_id' => $owner->id, 'name' => 'Trip']);
        $photo = $this->image($owner);
        $photo->delete();

        $this->actingAs($owner)
            ->post(route('photos.albums.add', $album), ['file_ids' => [$photo->id]]);

        $this->assertDatabaseMissing('album_file', ['album_id' => $album->id, 'file_id' => $photo->id]);
    }

    public function test_uploading_a_photo_when_a_file_named_photos_exists_does_not_500(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        // A non-folder item literally named "Photos" sitting at the root.
        File::factory()->for($owner, 'owner')->create(['name' => 'Photos', 'is_dir' => false]);

        $this->actingAs($owner)
            ->post(route('photos.upload'), ['files' => [UploadedFile::fake()->image('new.jpg')]])
            ->assertRedirect();
    }
}
