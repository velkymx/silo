<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_is_404_when_disabled(): void
    {
        config(['filemanager.allow_registration' => false]);

        $this->get('/register')->assertNotFound();
        $this->post('/register', [
            'name' => 'X', 'email' => 'x@x.test', 'password' => 'password123', 'password_confirmation' => 'password123',
        ])->assertNotFound();
    }

    public function test_registration_form_available_when_enabled(): void
    {
        config(['filemanager.allow_registration' => true]);

        $this->get('/register')->assertOk();
    }

    public function test_register_endpoint_is_rate_limited(): void
    {
        config(['filemanager.allow_registration' => true]);

        $hit429 = false;
        for ($i = 0; $i < 8; $i++) {
            $res = $this->post('/register', [
                'name' => 'X', 'email' => "dupe@x.test", 'password' => 'short', 'password_confirmation' => 'nope',
            ]);
            if ($res->status() === 429) {
                $hit429 = true;
                break;
            }
        }

        $this->assertTrue($hit429, 'Expected /register to rate-limit rapid attempts.');
    }
}
