<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeding_creates_an_admin_user(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $this->assertTrue((bool) $admin->is_admin);
    }

    public function test_seeding_is_idempotent(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $this->seed(\Database\Seeders\DatabaseSeeder::class);

        $this->assertSame(1, User::where('email', 'admin@example.com')->count());
    }
}
