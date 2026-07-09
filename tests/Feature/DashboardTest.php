<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_redirects_guests_to_login(): void
    {
        $this->get('/dashboard')->assertRedirect('/login');
    }

    public function test_dashboard_renders_with_a_jump_back_in_prop(): void
    {
        $this->asUser();

        $this->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard/Index')
                ->has('jumpBackIn'));
    }

    public function test_jump_back_in_is_the_most_recently_content_edited_file(): void
    {
        $user = $this->asUser();

        $this->withFile($user, ['name' => 'Old.txt', 'content_edited_at' => now()->subDays(3)]);
        $recent = $this->withFile($user, ['name' => 'Fresh.txt', 'content_edited_at' => now()->subMinutes(5)]);

        $this->get('/dashboard')->assertInertia(fn ($page) => $page
            ->where('jumpBackIn.id', $recent->id)
            ->where('jumpBackIn.title', 'Fresh.txt')
            ->where('jumpBackIn.type', 'file'));
    }

    public function test_jump_back_in_links_a_markdown_note_to_the_notes_surface(): void
    {
        $user = $this->asUser();

        $note = $this->withFile($user, [
            'name' => 'Journal',
            'mime' => 'text/markdown',
            'content_edited_at' => now(),
        ]);

        $this->get('/dashboard')->assertInertia(fn ($page) => $page
            ->where('jumpBackIn.id', $note->id)
            ->where('jumpBackIn.type', 'note')
            ->where('jumpBackIn.url', route('notes.index', ['open' => $note->id])));
    }

    public function test_jump_back_in_ignores_never_edited_and_other_users_files(): void
    {
        $user = $this->asUser();
        $other = User::factory()->create();

        $this->withFile($user, ['content_edited_at' => null]);
        $this->withFile($other, ['content_edited_at' => now()]);

        $this->get('/dashboard')->assertInertia(fn ($page) => $page->where('jumpBackIn', null));
    }
}
