<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Silo version
    |--------------------------------------------------------------------------
    |
    | The installed Silo release. Surfaced on the admin System Health card and
    | compared against the latest published release when the update check is
    | enabled.
    |
    */

    'version' => env('SILO_VERSION', '2.0.0'),

    /*
    |--------------------------------------------------------------------------
    | Update check
    |--------------------------------------------------------------------------
    |
    | Opt-in check that compares the installed version to the latest GitHub
    | release. Disabled by default so a locked-down or offline deployment never
    | phones home unexpectedly. Cached for `cache_hours` between network calls.
    | This checks *Silo* only — never the Laravel/PHP framework.
    |
    */

    'update_check' => [
        'enabled' => (bool) env('SILO_UPDATE_CHECK', false),
        'repo' => env('SILO_UPDATE_REPO', 'velkymx/silo'),
        'cache_hours' => 24,
    ],

];
