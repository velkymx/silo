<?php

namespace App\Services;

use Illuminate\Encryption\Encrypter;

/**
 * Symmetric encryption for vault secrets, isolated behind one service so the
 * storage layer never touches keys directly and a future zero-knowledge
 * (client-side) tier can replace this without schema changes.
 *
 * Uses AES-256-GCM (authenticated) with a dedicated VAULT_KEY. In production a
 * missing VAULT_KEY is fatal (fail-fast) rather than silently falling back to
 * APP_KEY — rotating APP_KEY would otherwise render every stored secret
 * permanently unrecoverable. Outside production the APP_KEY fallback is allowed
 * (with a warning) for developer convenience. NOTE: this is server-side
 * encryption — anyone with the key + database can decrypt. NOT zero-knowledge.
 */
class VaultCrypto
{
    private Encrypter $encrypter;

    public function __construct()
    {
        $this->encrypter = new Encrypter($this->resolveKey(), 'aes-256-gcm');
    }

    public function encrypt(string $plaintext): string
    {
        return $this->encrypter->encryptString($plaintext);
    }

    public function decrypt(string $ciphertext): string
    {
        return $this->encrypter->decryptString($ciphertext);
    }

    /**
     * Resolve the vault key. Requires a dedicated VAULT_KEY in production
     * (fail-fast); outside production, falls back to APP_KEY with a warning.
     * Supports the base64: prefix.
     */
    private function resolveKey(): string
    {
        $vaultKey = config('vault.key');

        if (! $vaultKey) {
            if (app()->isProduction()) {
                // Refuse to operate rather than silently encrypt with APP_KEY:
                // a later APP_KEY rotation would make every secret unrecoverable.
                throw new \RuntimeException('VAULT_KEY is not configured. Set a dedicated VAULT_KEY (php artisan vault:key) before using the vault in production.');
            }

            logger()->warning('VAULT_KEY is not set; vault secrets are encrypted with APP_KEY. Set VAULT_KEY before production.');
        }

        $key = (string) ($vaultKey ?: config('app.key'));

        if (str_starts_with($key, 'base64:')) {
            return base64_decode(substr($key, 7));
        }

        return $key;
    }
}
