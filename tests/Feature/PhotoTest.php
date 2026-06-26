<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PhotoTest extends TestCase
{
    use RefreshDatabase;

    private function image(User $u, string $name = 'pic.jpg'): File
    {
        return File::factory()->for($u, 'owner')->create([
            'name' => $name, 'mime' => 'image/jpeg', 'is_dir' => false,
        ]);
    }

    public function test_timeline_lists_only_owner_images(): void
    {
        $user = User::factory()->create();
        $this->image($user, 'a.jpg');
        File::factory()->for($user, 'owner')->create(['name' => 'doc.pdf', 'mime' => 'application/pdf']);
        $this->image(User::factory()->create(), 'other.jpg'); // someone else's

        $this->actingAs($user)->get(route('photos.index'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Photos/Index')->has('photos', 1));
    }

    public function test_upload_stores_image_in_photos_folder(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('photos.upload'), [
            'files' => [UploadedFile::fake()->image('beach.jpg')],
        ])->assertRedirect();

        $this->assertDatabaseHas('files', ['name' => 'beach.jpg', 'owner_id' => $user->id]);
        $this->assertDatabaseHas('files', ['name' => 'Photos', 'is_dir' => true, 'owner_id' => $user->id]);
    }

    public function test_album_create_add_cover_remove(): void
    {
        $user = User::factory()->create();
        $photo = $this->image($user);

        $this->actingAs($user)->post(route('photos.albums.store'), ['name' => 'Trip'])->assertRedirect();
        $album = Album::firstOrFail();

        $this->actingAs($user)->post(route('photos.albums.add', $album), ['file_ids' => [$photo->id]])->assertRedirect();
        $this->assertDatabaseHas('album_file', ['album_id' => $album->id, 'file_id' => $photo->id]);
        $this->assertSame($photo->id, $album->fresh()->cover_file_id); // auto cover

        $this->actingAs($user)->delete(route('photos.albums.remove', $album), ['file_ids' => [$photo->id]])->assertRedirect();
        $this->assertDatabaseMissing('album_file', ['album_id' => $album->id, 'file_id' => $photo->id]);
    }

    public function test_upload_sanitizes_special_characters_in_filename(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        // FileController::sanitizeFilename() allowlists to [\w\-. ()] and strips leading dots.
        // PhotoController skipped that step, so special chars survived in the DB name.
        $tmp = tempnam(sys_get_temp_dir(), 'photo_test_');
        imagejpeg(imagecreatetruecolor(1, 1), $tmp);
        $upload = new UploadedFile($tmp, 'shell<xss>.jpg', 'image/jpeg', null, true);

        $this->actingAs($user)->post(route('photos.upload'), [
            'files' => [$upload],
        ])->assertRedirect();

        $this->assertDatabaseMissing('files', ['name' => 'shell<xss>.jpg']);
        $this->assertDatabaseHas('files', ['name' => 'shell_xss_.jpg', 'owner_id' => $user->id]);
    }

    public function test_upload_strips_leading_dot_from_filename(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $tmp = tempnam(sys_get_temp_dir(), 'photo_test_');
        imagejpeg(imagecreatetruecolor(1, 1), $tmp);
        $upload = new UploadedFile($tmp, '.htaccess.jpg', 'image/jpeg', null, true);

        $this->actingAs($user)->post(route('photos.upload'), [
            'files' => [$upload],
        ])->assertRedirect();

        $this->assertDatabaseMissing('files', ['name' => '.htaccess.jpg']);
        $this->assertDatabaseHas('files', ['name' => 'htaccess.jpg', 'owner_id' => $user->id]);
    }

    public function test_cannot_touch_another_users_album(): void
    {
        $album = Album::create(['owner_id' => User::factory()->create()->id, 'name' => 'Private']);
        $this->actingAs(User::factory()->create())
            ->delete(route('photos.albums.destroy', $album))->assertForbidden();
    }
}
