<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TrashedExclusionTest extends TestCase
{
    use RefreshDatabase;

    public function test_trashed_file_is_excluded_from_tag_starred_recent_and_search(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $file = File::factory()->for($user, 'owner')->create([
            'name' => 'secret-report.txt', 'starred' => true,
        ]);
        $tag = Tag::create(['name' => 'work', 'owner_id' => $user->id]);
        $file->tags()->attach($tag->id);

        $file->delete(); // soft delete → trash

        // Starred view
        $this->actingAs($user)->get('/?starred=1')->assertInertia(
            fn ($p) => $p->where('files', fn ($files) => collect($files)->doesntContain('name', 'secret-report.txt'))
        );

        // Tag view
        $this->actingAs($user)->get('/?tag='.$tag->id)->assertInertia(
            fn ($p) => $p->where('files', fn ($files) => collect($files)->doesntContain('name', 'secret-report.txt'))
        );

        // Recent view
        $this->actingAs($user)->get('/?recent=1')->assertInertia(
            fn ($p) => $p->where('files', fn ($files) => collect($files)->doesntContain('name', 'secret-report.txt'))
        );

        // A trashed file must not be searchable.
        $this->assertFalse($file->shouldBeSearchable());
    }
}
