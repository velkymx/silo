<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // ME-10: per-request nonce so Vite-generated <script>/<style> tags can
        // satisfy the CSP without 'unsafe-inline'. Bootstrap's distributed
        // stylesheets don't carry the nonce, so style-src keeps 'unsafe-inline'
        // (Bootstrap 5 ships some inline styles for progress bars / spinners).
        $nonce = base64_encode(random_bytes(16));
        $request->attributes->set('csp_nonce', $nonce);
        Vite::useCspNonce($nonce);

        $response = $next($request);

        // Don't clobber the locked-down CSP that FileResponse sets on streamed
        // file bodies — only set headers on normal HTML/JSON responses.
        if ($response->headers->has('Content-Security-Policy')) {
            return $response;
        }

        foreach ($this->headers($request, $nonce) as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }

    /**
     * @return array<string, string>
     */
    private function headers(Request $request, string $nonce): array
    {
        return [
            'Content-Security-Policy' => $this->csp($request, $nonce),
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'same-origin',
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
        ];
    }

    private function csp(Request $request, string $nonce): string
    {
        $nonceAttr = "'nonce-{$nonce}'";
        // 'unsafe-eval' is required ONLY by jspreadsheet-ce's formula engine on
        // the document editor page — every other route gets a stricter policy.
        $script = ["'self'", $nonceAttr];
        if ($request->routeIs('files.edit')) {
            $script[] = "'unsafe-eval'";
        }
        // style-src must NOT carry the nonce: per the CSP spec a nonce makes
        // browsers ignore 'unsafe-inline', which Vue :style bindings and
        // Bootstrap's runtime inline styles (progress widths, spinner colors)
        // depend on — with both present every inline style was blocked.
        // Production styles are compiled <link> files covered by 'self'.
        $style = ["'self'", "'unsafe-inline'"];
        $font = ["'self'", 'data:'];
        $connect = ["'self'"];

        // Vite dev server (HMR) only in local.
        if (app()->environment('local')) {
            $script[] = "'unsafe-inline'";
            $script[] = 'http://localhost:5173';
            $connect[] = 'http://localhost:5173';
            $connect[] = 'ws://localhost:5173';
        }

        $directives = [
            "default-src 'self'",
            'script-src '.implode(' ', $script),
            'style-src '.implode(' ', $style),
            'font-src '.implode(' ', $font),
            'connect-src '.implode(' ', $connect),
            // Bookmark previews/favicons hotlink these external services
            // (WordPress mShots screenshots, DuckDuckGo icons).
            "img-src 'self' data: blob: https://s.wordpress.com https://icons.duckduckgo.com",
            "media-src 'self' blob:",
            "worker-src 'self' blob:",
            "frame-src 'self'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ];

        return implode('; ', $directives);
    }
}
