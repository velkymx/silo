<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Liveness / hydration
    |--------------------------------------------------------------------------
    |
    | When a bookmark is created (or re-checked) a queued ProcessBookmark job
    | validates the URL responds 2xx/3xx, downloads the site favicon, and —
    | when screenshots are enabled — captures a page screenshot. Requires a
    | running queue worker.
    |
    */

    'http_timeout' => (int) env('BOOKMARK_HTTP_TIMEOUT', 8),

    'screenshots' => [
        // Needs spatie/browsershot + a headless Chromium (Puppeteer) on the host.
        'enabled' => (bool) env('BOOKMARK_SCREENSHOTS', false),
        // When no self-hosted screenshot exists, show an on-demand thumbnail from
        // WordPress mShots. Works for PUBLIC sites only and sends the full
        // bookmark URL to a third party, so it is OFF by default — opt in only
        // when every bookmark is a public site (BOOKMARK_SCREENSHOT_FALLBACK=true).
        'fallback' => (bool) env('BOOKMARK_SCREENSHOT_FALLBACK', false),
        'width' => (int) env('BOOKMARK_SCREENSHOT_WIDTH', 1366),
        'height' => (int) env('BOOKMARK_SCREENSHOT_HEIGHT', 768),
        // Chromium refuses to launch as root or inside most containers without
        // --no-sandbox; on by default since this runs server-side. Set false
        // only if you run the worker as a non-root user with a real sandbox.
        'no_sandbox' => (bool) env('BOOKMARK_CHROME_NO_SANDBOX', true),
        // Optional explicit node/chromium paths for Browsershot.
        'node_binary' => env('BOOKMARK_NODE_BINARY'),
        'chrome_path' => env('BOOKMARK_CHROME_PATH'),
    ],

];
