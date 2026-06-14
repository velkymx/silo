<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorageAnalyzerTest extends TestCase
{
    use RefreshDatabase;

    public function test_aggregates_folder_sizes_and_categories(): void
    {
        $user = User::factory()->create();
        $folder = File::factory()->for($user, 'owner')->folder()->create(['name' => 'Trip']);
        File::factory()->for($user, 'owner')->create(['name' => 'a.jpg', 'mime' => 'image/jpeg', 'size' => 1000, 'parent_id' => $folder->id]);
        File::factory()->for($user, 'owner')->create(['name' => 'b.jpg', 'mime' => 'image/jpeg', 'size' => 500, 'parent_id' => $folder->id]);

        $this->actingAs($user)->get(route('storage.index'))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Storage/Index')
                ->where('nodes', fn ($nodes) => collect($nodes)->firstWhere('id', $folder->id)['size'] === 1500)
                ->where('byCategory.image', 1500)
                ->has('largest', 2));
    }
}
