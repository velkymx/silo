<?php

namespace Tests\Feature;

use App\Models\Album;
use App\Models\File;
use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PolicyTest extends TestCase
{
    use RefreshDatabase;

    // ── AlbumPolicy ──────────────────────────────────────────────────────────

    public function test_non_owner_cannot_modify_album(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $album = Album::create(['name' => 'My Album', 'owner_id' => $owner->id]);

        $this->actingAs($other)
            ->delete("/photos/albums/{$album->id}")
            ->assertForbidden();
    }

    public function test_admin_can_modify_any_album(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $album = Album::create(['name' => 'Shared Album', 'owner_id' => $owner->id]);

        $this->actingAs($admin)
            ->delete("/photos/albums/{$album->id}")
            ->assertRedirect();
    }

    // ── SavedSearchPolicy ────────────────────────────────────────────────────

    public function test_non_owner_cannot_delete_saved_search(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $ss = SavedSearch::create(['owner_id' => $owner->id, 'name' => 'My search', 'params' => ['search' => 'x']]);

        $this->actingAs($other)
            ->delete("/saved-searches/{$ss->id}")
            ->assertForbidden();
    }

    public function test_owner_can_delete_own_saved_search(): void
    {
        $owner = User::factory()->create();
        $ss = SavedSearch::create(['owner_id' => $owner->id, 'name' => 'Mine', 'params' => ['search' => 'x']]);

        $this->actingAs($owner)
            ->delete("/saved-searches/{$ss->id}")
            ->assertRedirect();
    }

    // ── TestCase helpers ─────────────────────────────────────────────────────

    public function test_as_user_helper_creates_and_authenticates(): void
    {
        $user = $this->asUser();
        $this->assertInstanceOf(User::class, $user);
        $this->assertAuthenticatedAs($user);
    }

    public function test_as_admin_helper_creates_admin(): void
    {
        $admin = $this->asAdmin();
        $this->assertTrue((bool) $admin->is_admin);
        $this->assertAuthenticatedAs($admin);
    }

    public function test_with_file_helper_creates_file_for_user(): void
    {
        $user = $this->asUser();
        $file = $this->withFile($user);
        $this->assertInstanceOf(File::class, $file);
        $this->assertSame($user->id, $file->owner_id);
    }

    public function test_with_folder_helper_creates_folder_for_user(): void
    {
        $user = $this->asUser();
        $folder = $this->withFolder($user);
        $this->assertTrue((bool) $folder->is_dir);
        $this->assertSame($user->id, $folder->owner_id);
    }
}
