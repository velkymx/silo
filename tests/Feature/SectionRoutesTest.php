<?php

namespace Tests\Feature;

use App\Models\File;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * /recent and /starred render the same Files/Index shell as `/`, scoped by
 * their route-default section (mirroring /shared and /trash).
 */
class SectionRoutesTest extends TestCase
{
    use RefreshDatabase;

    public function test_starred_renders_the_files_shell_scoped_to_starred_items(): void
    {
        $user = User::factory()->create();
        File::factory()->for($user, 'owner')->create(['name' => 'kept.pdf', 'starred' => true]);
        File::factory()->for($user, 'owner')->create(['name' => 'plain.pdf', 'starred' => false]);

        $this->actingAs($user)->get(route('starred.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Files/Index')
                ->where('section', 'starred')
                ->where('starredOnly', true)
                ->where('flat', true)
                ->has('files', 1)
                ->where('files.0.name', 'kept.pdf'));
    }

    public function test_starred_only_shows_the_users_own_items(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        File::factory()->for($other, 'owner')->create(['name' => 'theirs.pdf', 'starred' => true]);

        $this->actingAs($user)->get(route('starred.index'))
            ->assertInertia(fn ($page) => $page->component('Files/Index')->has('files', 0));
    }

    public function test_recent_renders_the_files_shell_scoped_to_recent_uploads(): void
    {
        $user = User::factory()->create();
        File::factory()->for($user, 'owner')->create(['name' => 'fresh.pdf']);

        $this->actingAs($user)->get(route('files.recent'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Files/Index')
                ->where('section', 'recent')
                ->where('recentOnly', true)
                ->where('flat', true)
                ->has('files', 1));
    }

    public function test_section_routes_require_authentication(): void
    {
        $this->get('/recent')->assertRedirect('/login');
        $this->get('/starred')->assertRedirect('/login');
    }
}
