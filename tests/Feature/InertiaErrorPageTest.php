<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InertiaErrorPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_share_link_renders_the_inertia_error_page(): void
    {
        $response = $this->get('/s/nonexistent-token');

        $response->assertStatus(404);
        $response->assertInertia(fn ($page) => $page
            ->component('Errors/Error')
            ->where('status', 404)
        );
    }

    public function test_unmatched_route_renders_the_inertia_error_page(): void
    {
        $response = $this->get('/this-route-does-not-exist');

        $response->assertStatus(404);
        $response->assertInertia(fn ($page) => $page->component('Errors/Error'));
    }

    public function test_api_style_request_still_gets_json(): void
    {
        $response = $this->getJson('/s/nonexistent-token');

        $response->assertStatus(404);
        $response->assertHeader('content-type', 'application/json');
    }
}
