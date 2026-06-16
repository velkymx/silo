<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AvatarTest extends TestCase
{
    use RefreshDatabase;

    private function png(int $w, int $h): string
    {
        $img = imagecreatetruecolor($w, $h);
        ob_start();
        imagepng($img);
        $bytes = ob_get_clean();
        imagedestroy($img);

        return $bytes;
    }

    public function test_user_can_upload_an_avatar_normalized_to_a_square(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('profile.avatar'), [
            'avatar' => \Illuminate\Http\UploadedFile::fake()->createWithContent('me.png', $this->png(400, 250)),
        ])->assertRedirect();

        $user->refresh();
        $this->assertNotNull($user->avatar_path);
        Storage::disk('public')->assertExists($user->avatar_path);

        $info = getimagesizefromstring(Storage::disk('public')->get($user->avatar_path));
        $this->assertSame(256, $info[0]);
        $this->assertSame(256, $info[1]);
    }

    public function test_replacing_avatar_removes_the_old_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $post = fn () => $this->actingAs($user)->post(route('profile.avatar'), [
            'avatar' => \Illuminate\Http\UploadedFile::fake()->createWithContent('a.png', $this->png(300, 300)),
        ]);

        $post();
        $first = $user->fresh()->avatar_path;
        $post();
        $second = $user->fresh()->avatar_path;

        $this->assertNotSame($first, $second);
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);
    }

    public function test_avatar_route_serves_and_404s_when_absent(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $viewer = User::factory()->create();

        $this->actingAs($viewer)->get(route('users.avatar', $owner))->assertNotFound();

        $this->actingAs($owner)->post(route('profile.avatar'), [
            'avatar' => \Illuminate\Http\UploadedFile::fake()->createWithContent('a.png', $this->png(300, 300)),
        ]);

        // Any authenticated user can fetch another user's avatar.
        $this->actingAs($viewer)->get(route('users.avatar', $owner->fresh()))->assertOk();
    }

    public function test_avatar_url_is_shared_to_the_frontend(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $this->actingAs($user)->post(route('profile.avatar'), [
            'avatar' => \Illuminate\Http\UploadedFile::fake()->createWithContent('a.png', $this->png(300, 300)),
        ]);

        $this->actingAs($user->fresh())->get('/profile')->assertInertia(
            fn (Assert $p) => $p->where('user.avatar_url', fn ($u) => is_string($u) && str_contains($u, '/avatars/'))
        );
    }
}
