<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_html_responses_carry_security_headers(): void
    {
        $user = User::factory()->create();

        $res = $this->actingAs($user)->get(route('files.index'));

        $res->assertOk();
        $res->assertHeader('X-Content-Type-Options', 'nosniff');
        $res->assertHeader('X-Frame-Options', 'DENY');
        $res->assertHeader('Referrer-Policy', 'same-origin');
        $this->assertStringContainsString("default-src 'self'", $res->headers->get('Content-Security-Policy'));
        $this->assertStringContainsString('frame-ancestors', $res->headers->get('Content-Security-Policy'));
    }
}
