<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FileTagTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_tags_creates_and_attaches_tags(): void
    {
        $user = User::factory()->create();
        $file = File::factory()->for($user, 'owner')->create();

        $this->actingAs($user)
            ->put(route('files.tags', $file), ['tags' => ['invoice', '2026']])
            ->assertRedirect();

        $this->assertCount(2, $file->fresh()->tags);
        $this->assertDatabaseHas('tags', ['owner_id' => $user->id, 'name' => 'invoice']);
    }

    public function test_sync_tags_reuses_existing_tag(): void
    {
        $user = User::factory()->create();
        $tag = Tag::factory()->create(['owner_id' => $user->id, 'name' => 'work']);
        $file = File::factory()->for($user, 'owner')->create();

        $this->actingAs($user)->put(route('files.tags', $file), ['tags' => ['work']]);

        $this->assertSame(1, Tag::where('owner_id', $user->id)->where('name', 'work')->count());
        $this->assertTrue($file->fresh()->tags->contains($tag->id));
    }

    public function test_sync_tags_detaches_removed_tags(): void
    {
        $user = User::factory()->create();
        $file = File::factory()->for($user, 'owner')->create();
        $this->actingAs($user)->put(route('files.tags', $file), ['tags' => ['a', 'b']]);

        $this->actingAs($user)->put(route('files.tags', $file), ['tags' => ['a']]);

        $names = $file->fresh()->tags->pluck('name')->all();
        $this->assertSame(['a'], $names);
    }

    public function test_filter_by_tag_lists_matching_files_across_folders(): void
    {
        $user = User::factory()->create();
        $folder = File::factory()->for($user, 'owner')->folder()->create();
        $tagged = File::factory()->for($user, 'owner')->create(['name' => 'taxes.pdf', 'parent_id' => $folder->id]);
        File::factory()->for($user, 'owner')->create(['name' => 'cat.png']);
        $this->actingAs($user)->put(route('files.tags', $tagged), ['tags' => ['finance']]);

        $tag = Tag::where('owner_id', $user->id)->where('name', 'finance')->firstOrFail();

        $this->actingAs($user)->get('/?tag='.$tag->id)->assertInertia(
            fn (Assert $page) => $page
                ->where('flat', true)
                ->where('activeTag.name', 'finance')
                ->has('files', 1)
                ->where('files.0.name', 'taxes.pdf')
        );
    }

    public function test_cannot_tag_another_users_file(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $file = File::factory()->for($owner, 'owner')->create();

        $this->actingAs($other)
            ->put(route('files.tags', $file), ['tags' => ['x']])
            ->assertForbidden();
    }
}
