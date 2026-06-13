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

        foreach ($this->headers() as $name => $value) {
            $response->headers->set($name, $value);
        }

        return $response;
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return [
            'Content-Security-Policy' => $this->csp(),
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'same-origin',
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()',
        ];
    }

    private function csp(): string
    {
        // 'unsafe-eval' is required by jspreadsheet-ce's formula engine.
        // 'unsafe-inline' (style) covers Bootstrap/Vue injected styles.
        // Google Fonts hosts the Material Icons used by the spreadsheet toolbar
        // (tracked for removal in H8 — drop these once fonts are bundled).
        $script = ["'self'", "'unsafe-eval'"];
        $style = ["'self'", "'unsafe-inline'", 'https://fonts.googleapis.com'];
        $font = ["'self'", 'data:', 'https://fonts.gstatic.com'];
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
            "img-src 'self' data: blob:",
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
