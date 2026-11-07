<?php

namespace App\Services\Rss;

use App\Services\Http\SafeUrl;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Throwable;

/**
 * Proxies remote images referenced by untrusted RSS content through our own
 * origin, so the strict `img-src 'self' ...` CSP is never violated and the
 * browser never contacts feed-controlled hosts (no tracking pixels, no IP or
 * referrer leaks).
 *
 * Only the app can mint a proxy URL — it is a signed route, so the endpoint
 * cannot be used as a general-purpose fetcher. Every fetch is guarded by
 * SafeUrl (SSRF), capped in size, and restricted to raster formats verified
 * by magic bytes: SVG/HTML are refused because they could execute when opened
 * from our origin. Successful bodies are cached on the local disk, keyed by
 * the SHA-256 of the source URL.
 */
class ImageProxy
{
    private const CACHE_DIR = 'rss-images';

    private const MAX_BYTES = 2 * 1024 * 1024;

    private SafeUrl $safeUrl;

    public function __construct(?SafeUrl $safeUrl = null)
    {
        $this->safeUrl = $safeUrl ?? new SafeUrl;
    }

    /**
     * Signed same-origin URL for a remote image, or null when the source is
     * empty or not an http(s) URL.
     */
    public function url(?string $source): ?string
    {
        $source = trim((string) $source);
        if ($source === '' || filter_var($source, FILTER_VALIDATE_URL) === false
            || ! preg_match('#^https?://#i', $source)) {
            return null;
        }

        return URL::signedRoute('rss.image', ['url' => $source]);
    }

    /**
     * Rewrite <img src> in already-sanitized article HTML to signed proxy
     * URLs. This runs on HtmlSanitizer output, whose allowlist normalises
     * attributes to double quotes and strips event handlers, so a targeted
     * match over the fragment is safe. Done at render time (not ingest) so a
     * rotated APP_KEY never strands stored signatures.
     */
    public function rewriteContent(?string $html): ?string
    {
        if ($html === null || $html === '') {
            return $html;
        }

        return preg_replace_callback(
            '/(<img\b[^>]*\bsrc=")([^"]*)(")/i',
            function (array $match): string {
                $source = html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $proxy = $this->url($source);

                return $proxy === null
                    ? $match[1].$match[2].$match[3]
                    : $match[1].htmlspecialchars($proxy, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8').$match[3];
            },
            $html,
        );
    }

    /**
     * Cached bytes for a remote image, or null when it cannot be fetched or
     * fails validation. Never throws on network/URL problems — a broken feed
     * image must not break a page render.
     */
    public function bytes(string $source): ?string
    {
        $path = self::CACHE_DIR.'/'.hash('sha256', $source);
        if (Storage::disk('local')->exists($path)) {
            $cached = Storage::disk('local')->get($path);

            return $this->mime($cached) === null ? null : $cached;
        }

        try {
            $this->safeUrl->assert($source);
            $response = Http::timeout(8)
                ->withOptions(['allow_redirects' => $this->safeUrl->allowRedirects(5)])
                ->get($source);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $body = $response->body();
        if ($body === '' || strlen($body) > self::MAX_BYTES || $this->mime($body) === null) {
            return null;
        }

        Storage::disk('local')->put($path, $body);

        return $body;
    }

    /**
     * Raster MIME from magic bytes, or null for anything executable/unknown.
     * The controller always serves this MIME with nosniff + a sandboxed CSP.
     */
    public function mime(string $bytes): ?string
    {
        if (str_starts_with($bytes, "\x89PNG\r\n\x1a\n")) {
            return 'image/png';
        }
        if (str_starts_with($bytes, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }
        if (str_starts_with($bytes, 'GIF87a') || str_starts_with($bytes, 'GIF89a')) {
            return 'image/gif';
        }
        if (str_starts_with($bytes, 'RIFF') && substr($bytes, 8, 4) === 'WEBP') {
            return 'image/webp';
        }

        return null;
    }
}
