<?php

namespace Tests\Feature;

use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SavedSearchFavoriteTest extends TestCase
{
    use RefreshDatabase;

    public function test_toggle_flips_is_favorite(): void
    {
        $user = User::factory()->create();
        $s = SavedSearch::create(['owner_id' => $user->id, 'name' => 'X', 'params' => ['q' => 'foo'], 'is_favorite' => false]);

        $this->actingAs($user)->post("/saved-searches/{$s->id}/favorite")->assertRedirect();
        $this->assertTrue($s->fresh()->is_favorite);

        $this->actingAs($user)->post("/saved-searches/{$s->id}/favorite")->assertRedirect();
        $this->assertFalse($s->fresh()->is_favorite);
    }

    public function test_toggle_other_user_saved_search_is_forbidden(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $s = SavedSearch::create(['owner_id' => $owner->id, 'name' => 'X', 'params' => ['q' => 'foo']]);

        $this->actingAs($other)->post("/saved-searches/{$s->id}/favorite")->assertForbidden();
        $this->assertFalse($s->fresh()->is_favorite);
    }

    public function test_store_can_create_already_favorite(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->post('/saved-searches', ['name' => 'Pinned', 'params' => ['q' => 'foo'], 'is_favorite' => true])
            ->assertRedirect();

        $s = SavedSearch::where('name', 'Pinned')->first();
        $this->assertTrue($s->is_favorite);
    }
}
