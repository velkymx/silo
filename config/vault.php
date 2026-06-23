<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Vault encryption key
    |--------------------------------------------------------------------------
    |
    | Dedicated 32-byte key (base64:) used by App\Services\VaultCrypto to encrypt
    | secrets at rest with AES-256-GCM. Generate one with `php artisan vault:key`.
    | If unset, the vault falls back to APP_KEY — functional, but rotating APP_KEY
    | (e.g. for sessions) would then invalidate every stored secret. Set this in
    | production. This is server-side encryption, NOT zero-knowledge.
    |
    */

    'key' => env('VAULT_KEY'),

];
