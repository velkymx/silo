<?php

namespace Tests\Feature;

use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SmartFolderTest extends TestCase
{
    use RefreshDatabase;

    public function test_save_and_shared_to_sidebar(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('saved-searches.store'), [
            'name' => 'Big images',
            'params' => ['ftype' => 'image', 'size_min' => 5, 'junk' => 'x'],
        ])->assertRedirect();

        $s = SavedSearch::firstOrFail();
        $this->assertSame('Big images', $s->name);
        $this->assertEqualsCanonicalizing(['ftype' => 'image', 'size_min' => 5], $s->params); // junk stripped

        // Shared on every page for the sidebar.
        $this->actingAs($user)->get('/')->assertInertia(fn ($p) => $p->has('savedSearches', 1));
    }

    public function test_cannot_delete_another_users_smart_folder(): void
    {
        $s = SavedSearch::create(['owner_id' => User::factory()->create()->id, 'name' => 'x', 'params' => []]);
        $this->actingAs(User::factory()->create())
            ->delete(route('saved-searches.destroy', $s))->assertForbidden();
    }
}
