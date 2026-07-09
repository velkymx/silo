<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Vault encryption key
    |--------------------------------------------------------------------------
    |
    | Dedicated 32-byte key (base64:) used by App\Services\VaultCrypto to encrypt
    | secrets at rest with AES-256-GCM. Generate one with `php artisan vault:key`.
    | REQUIRED in production: VaultCrypto fails fast when it is unset there, rather
    | than falling back to APP_KEY (rotating APP_KEY would otherwise invalidate
    | every stored secret). Outside production the APP_KEY fallback is allowed for
    | convenience. This is server-side encryption, NOT zero-knowledge.
    |
    */

    'key' => env('VAULT_KEY'),

];
