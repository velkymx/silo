<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_a_new_admin(): void
    {
        $this->artisan('app:create-admin', ['--email' => 'boss@x.test', '--password' => 'secret123'])
            ->assertSuccessful();

        $user = User::where('email', 'boss@x.test')->firstOrFail();
        $this->assertTrue((bool) $user->is_admin);
    }

    public function test_promotes_existing_user_without_password(): void
    {
        User::factory()->create(['email' => 'u@x.test', 'is_admin' => false]);

        $this->artisan('app:create-admin', ['--email' => 'u@x.test'])->assertSuccessful();

        $this->assertTrue((bool) User::where('email', 'u@x.test')->value('is_admin'));
    }

    public function test_fails_for_new_user_without_password(): void
    {
        $this->artisan('app:create-admin', ['--email' => 'new@x.test'])->assertFailed();
        $this->assertDatabaseMissing('users', ['email' => 'new@x.test']);
    }
}
