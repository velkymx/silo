<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DirectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_lists_people(): void
    {
        $user = User::factory()->create(['name' => 'Viewer']);
        User::factory()->create(['name' => 'Alice', 'department' => 'Engineering']);

        $this->actingAs($user)->get(route('directory.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Directory/Index')
                ->has('people', 2)
                ->has('departments'));
    }

    public function test_index_search_filters_by_name(): void
    {
        $user = User::factory()->create(['name' => 'Zed']);
        User::factory()->create(['name' => 'Aaron']);

        $this->actingAs($user)->get(route('directory.index', ['search' => 'aaro']))
            ->assertInertia(fn ($page) => $page->has('people', 1)->where('people.0.name', 'Aaron'));
    }

    public function test_index_filters_by_department(): void
    {
        $user = User::factory()->create(['department' => 'Sales']);
        User::factory()->create(['name' => 'Eng Person', 'department' => 'Engineering']);

        $this->actingAs($user)->get(route('directory.index', ['department' => 'Engineering']))
            ->assertInertia(fn ($page) => $page->has('people', 1)->where('people.0.name', 'Eng Person'));
    }

    public function test_show_renders_the_profile_page(): void
    {
        $user = User::factory()->create();
        $manager = User::factory()->create(['name' => 'Boss']);
        $person = User::factory()->create([
            'name' => 'Report', 'title' => 'Engineer', 'department' => 'Eng',
            'bio' => 'Builds things', 'manager_id' => $manager->id,
        ]);

        $this->actingAs($user)->get(route('directory.show', $person))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Directory/Profile')
                ->where('person.title', 'Engineer')
                ->where('person.bio', 'Builds things')
                ->where('person.manager.name', 'Boss')
                ->has('wall'));
    }

    public function test_card_returns_the_pane_json(): void
    {
        $user = User::factory()->create();
        $person = User::factory()->create(['name' => 'Pane', 'title' => 'Engineer']);

        $this->actingAs($user)->getJson(route('directory.card', $person))
            ->assertOk()
            ->assertJsonPath('person.title', 'Engineer');
    }

    public function test_user_can_self_edit_profile_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name, 'email' => $user->email,
            'title' => 'Designer', 'department' => 'Product', 'phone' => '555-0100',
            'location' => 'Remote', 'bio' => 'Hi', 'start_date' => '2025-01-15',
        ])->assertRedirect();

        $user->refresh();
        $this->assertSame('Designer', $user->title);
        $this->assertSame('Product', $user->department);
        $this->assertSame('2025-01-15', $user->start_date->format('Y-m-d'));
    }
}
