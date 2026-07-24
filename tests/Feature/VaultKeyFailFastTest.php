<?php

namespace Tests\Feature;

use App\Services\VaultCrypto;
use Tests\TestCase;

class VaultKeyFailFastTest extends TestCase
{
    private function asProduction(): void
    {
        config(['app.env' => 'production']);
        app()->detectEnvironment(fn () => 'production');
    }

    public function test_production_without_vault_key_refuses_to_operate(): void
    {
        $this->asProduction();
        config(['vault.key' => null]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('VAULT_KEY is not configured');

        new VaultCrypto();
    }

    public function test_production_with_vault_key_encrypts_and_decrypts(): void
    {
        $this->asProduction();
        config(['vault.key' => 'base64:'.base64_encode(random_bytes(32))]);

        $crypto = new VaultCrypto();
        $cipher = $crypto->encrypt('s3cr3t');

        $this->assertNotSame('s3cr3t', $cipher);
        $this->assertSame('s3cr3t', $crypto->decrypt($cipher));
    }

    public function test_non_production_falls_back_to_app_key(): void
    {
        // Default test env is not production; a missing VAULT_KEY is tolerated.
        config(['vault.key' => null]);

        $crypto = new VaultCrypto();

        $this->assertSame('hello', $crypto->decrypt($crypto->encrypt('hello')));
    }
}
