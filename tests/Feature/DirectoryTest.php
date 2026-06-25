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

    public function test_show_returns_a_profile(): void
    {
        $user = User::factory()->create();
        $manager = User::factory()->create(['name' => 'Boss']);
        $person = User::factory()->create([
            'name' => 'Report', 'title' => 'Engineer', 'department' => 'Eng',
            'bio' => 'Builds things', 'manager_id' => $manager->id,
        ]);

        $this->actingAs($user)->getJson(route('directory.show', $person))
            ->assertOk()
            ->assertJsonPath('person.title', 'Engineer')
            ->assertJsonPath('person.bio', 'Builds things')
            ->assertJsonPath('person.manager.name', 'Boss');
    }

    public function test_user_can_self_edit_profile_fields(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('profile.update'), [
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
