<?php

namespace App\Casts;

use App\Services\VaultCrypto;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Eloquent cast that transparently encrypts a column with VaultCrypto on write
 * and decrypts on read, so the model holds plaintext in memory but only
 * ciphertext is ever persisted.
 *
 * @implements CastsAttributes<string|null, string|null>
 */
class VaultEncrypted implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : app(VaultCrypto::class)->decrypt($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : app(VaultCrypto::class)->encrypt((string) $value);
    }
}
