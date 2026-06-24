<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Don't clobber the locked-down CSP that FileResponse sets on streamed
        // file bodies — only set headers on normal HTML/JSON responses.
        if ($response->headers->has('Content-Security-Policy')) {
            return $response;
        }

        foreach ($this->headers($request) as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }

    /**
     * @return array<string, string>
     */
    private function headers(Request $request): array
    {
        return [
            'Content-Security-Policy' => $this->csp($request),
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'same-origin',
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
        ];
    }

    private function csp(Request $request): string
    {
        // 'unsafe-eval' is required ONLY by jspreadsheet-ce's formula engine on
        // the document editor page — every other route gets a stricter policy.
        // 'unsafe-inline' (style) covers Bootstrap/Vue injected styles.
        // All fonts/icons are bundled locally — no external origins (air-gap safe).
        $script = ["'self'"];
        if ($request->routeIs('files.edit')) {
            $script[] = "'unsafe-eval'";
        }
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
