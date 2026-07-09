<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AboutPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_about_redirects_guests_to_login(): void
    {
        $this->get('/about')->assertRedirect('/login');
    }

    public function test_about_renders_with_version_and_developer(): void
    {
        config(['silo.version' => '2.1.0']);
        $this->asUser();

        $this->get('/about')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('About')
                ->where('version', '2.1.0')
                ->has('developer.name')
                ->has('developer.hire_url')
                ->has('developer.linkedin'));
    }
}
