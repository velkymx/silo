<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\ShareLink;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ShareLinkTest extends TestCase
{
    use RefreshDatabase;

    private function storedFile(User $owner): File
    {
        Storage::disk('public')->put($path = 'uploads/'.$owner->id.'/a.png', 'imgbytes');

        return File::factory()->for($owner, 'owner')->create([
            'name' => 'a.png', 'path' => $path, 'mime' => 'image/png',
        ]);
    }

    public function test_owner_can_create_a_link_and_guest_can_view_and_download(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $file = $this->storedFile($owner);

        $this->actingAs($owner)
            ->postJson(route('files.links.store', $file), ['allow_download' => true])
            ->assertOk();

        $link = ShareLink::firstOrFail();

        // Guest (not authenticated) can reach the public page, preview, and download.
        $this->get(route('shares.public.show', $link->token))->assertOk()->assertInertia(
            fn (Assert $p) => $p->component('Public/Share')->where('locked', false)->where('allow_download', true)
        );
        $this->get(route('shares.public.raw', $link->token))->assertOk();
        $this->get(route('shares.public.download', $link->token))->assertOk();
    }

    public function test_view_only_link_blocks_download(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $file = $this->storedFile($owner);
        $link = ShareLink::factory()->viewOnly()->create(['file_id' => $file->id]);

        $this->get(route('shares.public.raw', $link->token))->assertOk();
        $this->get(route('shares.public.download', $link->token))->assertForbidden();
    }

    public function test_expired_link_is_not_found(): void
    {
        Storage::fake('public');
        $file = $this->storedFile(User::factory()->create());
        $link = ShareLink::factory()->expired()->create(['file_id' => $file->id]);

        $this->get(route('shares.public.show', $link->token))->assertNotFound();
        $this->get(route('shares.public.raw', $link->token))->assertNotFound();
    }

    public function test_password_protected_link_requires_unlock(): void
    {
        Storage::fake('public');
        $file = $this->storedFile(User::factory()->create());
        $link = ShareLink::factory()->protectedWith('hunter2')->create(['file_id' => $file->id]);

        // Locked landing; raw blocked until unlocked.
        $this->get(route('shares.public.show', $link->token))->assertInertia(
            fn (Assert $p) => $p->where('locked', true)
        );
        $this->get(route('shares.public.raw', $link->token))->assertForbidden();

        // Wrong password rejected.
        $this->post(route('shares.public.unlock', $link->token), ['password' => 'nope'])
            ->assertSessionHasErrors('password');

        // Correct password unlocks the session, then raw works.
        $this->post(route('shares.public.unlock', $link->token), ['password' => 'hunter2'])
            ->assertRedirect(route('shares.public.show', $link->token));
        $this->get(route('shares.public.raw', $link->token))->assertOk();
    }

    public function test_unlock_is_rate_limited_against_brute_force(): void
    {
        Storage::fake('public');
        $file = $this->storedFile(User::factory()->create());
        $link = ShareLink::factory()->protectedWith('hunter2')->create(['file_id' => $file->id]);

        $hit429 = false;
        for ($i = 0; $i < 7; $i++) {
            $res = $this->post(route('shares.public.unlock', $link->token), ['password' => 'wrong']);
            if ($res->status() === 429) {
                $hit429 = true;
                break;
            }
        }

        $this->assertTrue($hit429, 'Expected the unlock endpoint to rate-limit brute-force attempts.');
    }

    public function test_unlock_stores_session_keyed_by_link_id_not_token(): void
    {
        Storage::fake('public');
        $file = $this->storedFile(User::factory()->create());
        $link = ShareLink::factory()->protectedWith('hunter2')->create(['file_id' => $file->id]);

        $this->post(route('shares.public.unlock', $link->token), ['password' => 'hunter2']);

        $this->assertTrue(session()->has("share_unlocked.{$link->id}"));
        $this->assertFalse(session()->has("share_unlocked.{$link->token}"));
    }

    public function test_deleting_the_file_kills_the_link(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $file = $this->storedFile($owner);
        $link = ShareLink::factory()->create(['file_id' => $file->id]);

        $file->delete(); // soft delete

        $this->get(route('shares.public.show', $link->token))->assertNotFound();
    }

    public function test_non_sharer_cannot_create_a_link(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $file = $this->storedFile($owner);

        $this->actingAs($other)
            ->postJson(route('files.links.store', $file), ['allow_download' => true])
            ->assertForbidden();
    }

    public function test_owner_can_revoke_a_link(): void
    {
        Storage::fake('public');
        $owner = User::factory()->create();
        $file = $this->storedFile($owner);
        $link = ShareLink::factory()->create(['file_id' => $file->id]);

        $this->actingAs($owner)
            ->deleteJson(route('files.links.destroy', [$file, $link]))
            ->assertOk();

        $this->assertDatabaseMissing('share_links', ['id' => $link->id]);
        $this->get(route('shares.public.show', $link->token))->assertNotFound();
    }
}
