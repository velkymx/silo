<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdvancedSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_filters_by_type_and_size(): void
    {
        $user = User::factory()->create();
        File::factory()->for($user, 'owner')->create(['name' => 'big.png', 'mime' => 'image/png', 'size' => 5 * 1024 * 1024]);
        File::factory()->for($user, 'owner')->create(['name' => 'small.png', 'mime' => 'image/png', 'size' => 100]);
        File::factory()->for($user, 'owner')->create(['name' => 'doc.pdf', 'mime' => 'application/pdf', 'size' => 9 * 1024 * 1024]);

        $this->actingAs($user)->get('/?ftype=image&size_min=1')
            ->assertOk()
            ->assertInertia(fn ($p) => $p->where('advanced', true)->has('files', 1)
                ->where('files.0.name', 'big.png'));
    }

    public function test_filters_by_date_range(): void
    {
        $user = User::factory()->create();
        $old = File::factory()->for($user, 'owner')->create(['name' => 'old.txt']);
        $old->forceFill(['created_at' => '2020-01-01'])->saveQuietly();
        File::factory()->for($user, 'owner')->create(['name' => 'new.txt']); // today

        $this->actingAs($user)->get('/?date_from=2026-01-01')
            ->assertOk()
            ->assertInertia(fn ($p) => $p->has('files', 1)->where('files.0.name', 'new.txt'));
    }

    public function test_filters_by_tag_and_text(): void
    {
        $user = User::factory()->create();
        $tag = Tag::create(['owner_id' => $user->id, 'name' => 'work']);
        $a = File::factory()->for($user, 'owner')->create(['name' => 'report-work.txt']);
        $a->tags()->attach($tag);
        File::factory()->for($user, 'owner')->create(['name' => 'report-home.txt']);

        $this->actingAs($user)->get("/?size_min=0&search=report&tag={$tag->id}")
            ->assertOk()
            ->assertInertia(fn ($p) => $p->has('files', 1)->where('files.0.name', 'report-work.txt'));
    }
}
