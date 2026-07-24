<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FolderIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_folders_index_returns_user_folders(): void
    {
        $user = User::factory()->create();
        $a = File::factory()->for($user, 'owner')->folder()->create(['name' => 'Alpha', 'parent_id' => null]);
        $b = File::factory()->for($user, 'owner')->folder()->create(['name' => 'Beta', 'parent_id' => null]);
        // A folder owned by someone else — must NOT be visible.
        File::factory()->folder()->create(['name' => 'Other', 'parent_id' => null]);

        $this->actingAs($user)
            ->getJson(route('folders.index'))
            ->assertOk()
            ->assertJsonCount(2)
            ->assertJsonFragment(['id' => $a->id, 'name' => 'Alpha', 'parent_id' => null])
            ->assertJsonFragment(['id' => $b->id, 'name' => 'Beta', 'parent_id' => null]);
    }

    public function test_folders_index_filters_by_parent(): void
    {
        $user = User::factory()->create();
        $parent = File::factory()->for($user, 'owner')->folder()->create(['name' => 'Parent', 'parent_id' => null]);
        File::factory()->for($user, 'owner')->folder()->create(['name' => 'Child1', 'parent_id' => $parent->id]);
        File::factory()->for($user, 'owner')->folder()->create(['name' => 'Child2', 'parent_id' => $parent->id]);
        File::factory()->for($user, 'owner')->folder()->create(['name' => 'Uncle', 'parent_id' => null]);

        $this->actingAs($user)
            ->getJson(route('folders.index', ['parent' => $parent->id]))
            ->assertOk()
            ->assertJsonCount(2);
    }

    public function test_folders_index_filters_by_search(): void
    {
        $user = User::factory()->create();
        File::factory()->for($user, 'owner')->folder()->create(['name' => 'Reports 2026', 'parent_id' => null]);
        File::factory()->for($user, 'owner')->folder()->create(['name' => 'Invoices', 'parent_id' => null]);
        File::factory()->for($user, 'owner')->folder()->create(['name' => 'Photos', 'parent_id' => null]);

        $this->actingAs($user)
            ->getJson(route('folders.index', ['q' => 'repo']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonFragment(['name' => 'Reports 2026']);
    }

    public function test_folders_index_returns_200_max(): void
    {
        $user = User::factory()->create();
        // 250 folders — endpoint must cap at 200 to keep the payload bounded.
        for ($i = 0; $i < 250; $i++) {
            File::create([
                'name' => sprintf('F%03d', $i),
                'path' => sprintf('F%03d', $i),
                'disk' => 'public',
                'is_dir' => true,
                'parent_id' => null,
                'owner_id' => $user->id,
            ]);
        }

        $this->actingAs($user)
            ->getJson(route('folders.index'))
            ->assertOk()
            ->assertJsonCount(200);
    }

    public function test_folders_index_is_gated_to_authenticated_users(): void
    {
        $this->getJson(route('folders.index'))
            ->assertUnauthorized();
    }
}
