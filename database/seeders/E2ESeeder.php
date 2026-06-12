<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class E2ESeeder extends Seeder
{
    /**
     * Deterministic account for the end-to-end suite.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'e2e@example.test'],
            ['name' => 'E2E Admin', 'password' => Hash::make('password'), 'is_admin' => true],
        );
    }
}
