<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchScopeTest extends TestCase
{
    use RefreshDatabase;

    public function test_folder_scope_limits_to_subtree(): void
    {
        $user = User::factory()->create();
        $folder = File::factory()->for($user, 'owner')->folder()->create(['name' => 'Trip']);
        $sub = File::factory()->for($user, 'owner')->folder()->create(['name' => 'Day1', 'parent_id' => $folder->id]);

        File::factory()->for($user, 'owner')->create(['name' => 'report.txt', 'parent_id' => $folder->id]);
        File::factory()->for($user, 'owner')->create(['name' => 'report-sub.txt', 'parent_id' => $sub->id]);
        File::factory()->for($user, 'owner')->create(['name' => 'report-outside.txt', 'parent_id' => null]);

        // Scoped to folder => folder + descendants only (2), not the outside one.
        $this->actingAs($user)->get("/?search=report&scope=folder&folder={$folder->id}")
            ->assertInertia(fn ($p) => $p->has('files', 2)->where('filters.scope', 'folder'));

        // All folders => all three.
        $this->actingAs($user)->get('/?search=report')
            ->assertInertia(fn ($p) => $p->has('files', 3));
    }
}
