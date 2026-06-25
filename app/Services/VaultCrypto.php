<?php

namespace App\Services;

use Illuminate\Encryption\Encrypter;

/**
 * Symmetric encryption for vault secrets, isolated behind one service so the
 * storage layer never touches keys directly and a future zero-knowledge
 * (client-side) tier can replace this without schema changes.
 *
 * Uses AES-256-GCM (authenticated) with a dedicated VAULT_KEY when set, falling
 * back to APP_KEY. NOTE: this is server-side encryption — anyone with the key +
 * database can decrypt. It is NOT zero-knowledge.
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

    /** Prefer the dedicated VAULT_KEY; fall back to APP_KEY. Supports base64: prefix. */
    private function resolveKey(): string
    {
        $key = (string) (config('vault.key') ?: config('app.key'));

        if (str_starts_with($key, 'base64:')) {
            return base64_decode(substr($key, 7));
        }

        return $key;
    }
}
