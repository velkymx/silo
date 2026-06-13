<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class StarredTest extends TestCase
{
    use RefreshDatabase;

    public function test_star_toggles_on_and_off(): void
    {
        $user = User::factory()->create();
        $file = File::factory()->for($user, 'owner')->create(['starred' => false]);

        $this->actingAs($user)->post(route('files.star', $file))->assertRedirect();
        $this->assertTrue($file->fresh()->starred);

        $this->actingAs($user)->post(route('files.star', $file))->assertRedirect();
        $this->assertFalse($file->fresh()->starred);
    }

    public function test_starred_filter_lists_starred_files_and_folders(): void
    {
        $user = User::factory()->create();
        $folder = File::factory()->for($user, 'owner')->folder()->create(['name' => 'Fav Dir', 'starred' => true]);
        File::factory()->for($user, 'owner')->create(['name' => 'fav.txt', 'starred' => true, 'parent_id' => $folder->id]);
        File::factory()->for($user, 'owner')->create(['name' => 'plain.txt', 'starred' => false]);

        $this->actingAs($user)->get('/?starred=1')->assertInertia(
            fn (Assert $p) => $p
                ->where('starredOnly', true)
                ->where('flat', true)
                ->has('folders', 1)
                ->where('folders.0.name', 'Fav Dir')
                ->has('files', 1)
                ->where('files.0.name', 'fav.txt')
        );
    }

    public function test_cannot_star_another_users_file(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $file = File::factory()->for($owner, 'owner')->create();

        $this->actingAs($other)->post(route('files.star', $file))->assertForbidden();
    }

    public function test_index_exposes_starred_flag(): void
    {
        $user = User::factory()->create();
        File::factory()->for($user, 'owner')->create(['name' => 'a.txt', 'starred' => true]);

        $this->actingAs($user)->get('/')->assertInertia(
            fn (Assert $p) => $p->where('files.0.starred', true)
        );
    }
}
