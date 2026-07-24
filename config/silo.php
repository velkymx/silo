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

    /*
    |--------------------------------------------------------------------------
    | Developer / About page
    |--------------------------------------------------------------------------
    |
    | Shown on the in-app About page (footer link). Forks can point these at
    | their own maintainer, or blank them out.
    |
    */

    'developer' => [
        'name' => env('SILO_DEV_NAME', 'Alan Bollinger'),
        'title' => env('SILO_DEV_TITLE', 'Technology consultant — Laravel, JavaScript, and AI systems'),
        'hire_url' => env('SILO_DEV_HIRE_URL', 'https://blog.ajb.bz/hire-alan-bollinger/'),
        'linkedin' => env('SILO_DEV_LINKEDIN', 'https://www.linkedin.com/in/abollinger'),
    ],

];
