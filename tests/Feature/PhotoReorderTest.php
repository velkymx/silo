<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PhotoReorderTest extends TestCase
{
    use RefreshDatabase;

    private function photo(User $u, string $name): File
    {
        return File::factory()->for($u, 'owner')->create(['name' => $name, 'mime' => 'image/jpeg']);
    }

    public function test_reorder_persists_sort_order_and_drives_listing(): void
    {
        $user = User::factory()->create();
        $a = $this->photo($user, 'a.jpg');
        $b = $this->photo($user, 'b.jpg');
        $c = $this->photo($user, 'c.jpg');

        // Reorder to c, a, b.
        $this->actingAs($user)
            ->post(route('photos.reorder'), ['ids' => [$c->id, $a->id, $b->id]])
            ->assertRedirect();

        $this->assertSame(0, $c->fresh()->sort_order);
        $this->assertSame(1, $a->fresh()->sort_order);
        $this->assertSame(2, $b->fresh()->sort_order);

        // The Photos index reflects the manual order first.
        $this->actingAs($user)->get('/photos')->assertInertia(
            fn (Assert $p) => $p->where('photos.0.name', 'c.jpg')
                ->where('photos.1.name', 'a.jpg')
                ->where('photos.2.name', 'b.jpg')
        );
    }

    public function test_reorder_ignores_foreign_photos(): void
    {
        $user = User::factory()->create();
        $foreign = $this->photo(User::factory()->create(), 'x.jpg');

        $this->actingAs($user)
            ->post(route('photos.reorder'), ['ids' => [$foreign->id]])
            ->assertRedirect();

        $this->assertNull($foreign->fresh()->sort_order);
    }
}
