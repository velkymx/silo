<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BreakCrushTest extends TestCase
{
    use RefreshDatabase;

    public function test_break_crush_page_renders_for_authenticated_users(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/break/crush');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page->component('Break/Crush'));
    }

    public function test_break_crush_redirects_guests_to_login(): void
    {
        $response = $this->get('/break/crush');

        $response->assertRedirect('/login');
    }
}
