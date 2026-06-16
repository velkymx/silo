<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfilePasswordConfirmTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create([
            'email' => 'me@x.test',
            'password' => Hash::make('current-secret'),
        ]);
    }

    public function test_password_change_requires_current_password(): void
    {
        $user = $this->user();

        $this->actingAs($user)->from('/profile')->post('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
        ])->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('current-secret', $user->fresh()->password));
    }

    public function test_email_change_requires_current_password(): void
    {
        $user = $this->user();

        $this->actingAs($user)->from('/profile')->post('/profile', [
            'name' => $user->name,
            'email' => 'new@x.test',
        ])->assertSessionHasErrors('current_password');

        $this->assertSame('me@x.test', $user->fresh()->email);
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = $this->user();

        $this->actingAs($user)->from('/profile')->post('/profile', [
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
            'current_password' => 'wrong',
        ])->assertSessionHasErrors('current_password');
    }

    public function test_correct_current_password_allows_change(): void
    {
        $user = $this->user();

        $this->actingAs($user)->from('/profile')->post('/profile', [
            'name' => $user->name,
            'email' => 'new@x.test',
            'password' => 'brand-new-pass',
            'password_confirmation' => 'brand-new-pass',
            'current_password' => 'current-secret',
        ])->assertRedirect();

        $fresh = $user->fresh();
        $this->assertSame('new@x.test', $fresh->email);
        $this->assertTrue(Hash::check('brand-new-pass', $fresh->password));
    }

    public function test_name_only_change_does_not_require_current_password(): void
    {
        $user = $this->user();

        $this->actingAs($user)->from('/profile')->post('/profile', [
            'name' => 'Renamed',
            'email' => $user->email,
        ])->assertRedirect();

        $this->assertSame('Renamed', $user->fresh()->name);
    }
}
