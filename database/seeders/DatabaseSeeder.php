<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Creates (or refreshes) a single admin account so a clean rebuild has a
     * usable login. Override the defaults with ADMIN_EMAIL / ADMIN_PASSWORD /
     * ADMIN_NAME in `.env`. Idempotent — safe to re-run.
     */
    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@example.com');
        $password = env('ADMIN_PASSWORD', 'password');

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => env('ADMIN_NAME', 'Administrator'),
                'password' => Hash::make($password),
                'is_admin' => true,
            ],
        );

        $this->command?->info("Admin user ready: {$email}");
    }
}
