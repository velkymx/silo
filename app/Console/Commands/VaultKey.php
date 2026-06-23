<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('vault:key')]
#[Description('Generate a base64 32-byte VAULT_KEY for encrypting vault secrets')]
class VaultKey extends Command
{
    public function handle(): int
    {
        $key = 'base64:'.base64_encode(random_bytes(32));

        $this->info('Add this to your .env (keep it secret, back it up — losing it makes secrets unrecoverable):');
        $this->newLine();
        $this->line("VAULT_KEY={$key}");

        return self::SUCCESS;
    }
}
