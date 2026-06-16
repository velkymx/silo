<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

#[Signature('app:create-admin {--email=admin@example.com} {--password=} {--name=Administrator}')]
#[Description('Create or promote an admin user (idempotent — safe to run on every boot)')]
class CreateAdmin extends Command
{
    public function handle(): int
    {
        $email = (string) $this->option('email');
        $password = (string) $this->option('password');

        $existing = User::where('email', $email)->first();
        if ($existing) {
            if (! $existing->is_admin) {
                $existing->update(['is_admin' => true]);
                $this->info("Promoted existing user {$email} to admin.");
            } else {
                $this->info("Admin {$email} already exists — leaving untouched.");
            }

            return self::SUCCESS;
        }

        if ($password === '') {
            $this->error('No password set and user does not exist. Pass --password=… (or set ADMIN_PASSWORD).');

            return self::FAILURE;
        }

        User::create([
            'name' => (string) $this->option('name'),
            'email' => $email,
            'password' => Hash::make($password),
            'is_admin' => true,
        ]);

        $this->info("Created admin {$email}.");

        return self::SUCCESS;
    }
}
