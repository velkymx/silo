<?php

namespace Tests\Feature;

use App\Models\SavedSearch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SavedSearchesTest extends TestCase
{
    use RefreshDatabase;

    public function test_store_global_search_with_q(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->post('/saved-searches', [
                'name' => 'My Search',
                'params' => ['q' => 'laravel'],
            ])
            ->assertRedirect();

        $s = SavedSearch::where('name', 'My Search')->first();
        $this->assertNotNull($s);
        $this->assertSame(['q' => 'laravel'], $s->params);
        $this->assertTrue($s->isGlobal());
    }

    public function test_store_file_smart_folder_still_works(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->post('/saved-searches', [
                'name' => 'My Smart Folder',
                'params' => ['search' => 'report', 'ftype' => 'pdf'],
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Smart folder saved.');

        $s = SavedSearch::where('name', 'My Smart Folder')->first();
        $this->assertNotNull($s);
        $this->assertFalse($s->isGlobal());
    }

    public function test_store_strips_unknown_params(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)
            ->post('/saved-searches', [
                'name' => 'X',
                'params' => ['q' => 'hello', 'malicious' => 'drop table'],
            ])
            ->assertRedirect();

        $s = SavedSearch::where('name', 'X')->first();
        $this->assertSame(['q' => 'hello'], $s->params);
    }

    public function test_destroy_emits_correct_flash_for_global_vs_file(): void
    {
        $user = User::factory()->create();
        $global = SavedSearch::create(['owner_id' => $user->id, 'name' => 'G', 'params' => ['q' => 'foo']]);
        $file = SavedSearch::create(['owner_id' => $user->id, 'name' => 'F', 'params' => ['search' => 'bar']]);

        $this->actingAs($user)->delete("/saved-searches/{$global->id}")
            ->assertRedirect()
            ->assertSessionHas('success', 'Saved search removed.');

        $this->actingAs($user)->delete("/saved-searches/{$file->id}")
            ->assertRedirect()
            ->assertSessionHas('success', 'Smart folder removed.');
    }

    public function test_search_page_renders_saved_searches(): void
    {
        $user = User::factory()->create();
        SavedSearch::create(['owner_id' => $user->id, 'name' => 'Laravel stuff', 'params' => ['q' => 'laravel']]);
        SavedSearch::create(['owner_id' => $user->id, 'name' => 'PDFs', 'params' => ['search' => 'doc', 'ftype' => 'pdf']]);

        $this->actingAs($user)->get('/search')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Search/Index')
                ->where('savedSearches', fn ($arr) => count($arr) === 2)
            );
    }

    public function test_other_user_cannot_delete_saved_search(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $s = SavedSearch::create(['owner_id' => $owner->id, 'name' => 'X', 'params' => ['q' => 'foo']]);

        $this->actingAs($other)->delete("/saved-searches/{$s->id}")->assertForbidden();
    }

    public function test_is_global_helper(): void
    {
        $global = new SavedSearch(['params' => ['q' => 'x']]);
        $this->assertTrue($global->isGlobal());
        $file = new SavedSearch(['params' => ['search' => 'x']]);
        $this->assertFalse($file->isGlobal());
    }
}
