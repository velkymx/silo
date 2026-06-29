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

    public function test_csp_nonce_is_set_and_unique_per_request(): void
    {
        // ME-10: nonce-based CSP. Two requests get distinct nonces, both
        // appear in the script-src and style-src directives.
        $user = User::factory()->create();
        $a = $this->actingAs($user)->get(route('files.index'))->headers->get('Content-Security-Policy');
        $b = $this->actingAs($user)->get(route('files.index'))->headers->get('Content-Security-Policy');

        preg_match_all("/'nonce-([A-Za-z0-9+\/=]+)'/", $a, $ma);
        preg_match_all("/'nonce-([A-Za-z0-9+\/=]+)'/", $b, $mb);
        $this->assertNotEmpty($ma[1], 'No nonce in script-src/style-src of first response');
        $this->assertNotEmpty($mb[1], 'No nonce in script-src/style-src of second response');
        $this->assertNotSame($ma[1][0], $mb[1][0], 'Nonce reused across requests');
    }
}
