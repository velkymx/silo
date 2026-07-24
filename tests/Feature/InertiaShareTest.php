<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InertiaShareTest extends TestCase
{
    use RefreshDatabase;

    public function test_sidebar_props_omitted_on_non_sidebar_routes(): void
    {
        $user = User::factory()->create();

        // /avatars/{user} is a non-Inertia streaming route. Even if share()
        // runs, the sidebar gate must short-circuit (no DB hit, no crash).
        $resp = $this->actingAs($user)->get(route('users.avatar', $user));
        // 404 is the expected response (user has no avatar stored). The point
        // is: no 500 from a DB error caused by the middleware.
        $resp->assertNotFound();
    }

    public function test_storage_and_saved_searches_present_on_files_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('files.index'))
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->has('storage')
                ->has('savedSearches')
            );
    }

    public function test_storage_and_saved_searches_present_on_photos_index(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('photos.index'))
            ->assertSuccessful()
            ->assertInertia(fn ($page) => $page
                ->has('storage')
                ->has('savedSearches')
            );
    }
}
