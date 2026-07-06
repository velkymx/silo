<?php

namespace App\Services\Rss;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Fetch and cache a feed's favicon. Strategy:
 *
 *   1. Try `<link rel="icon">` / `<link rel="apple-touch-icon">` on the
 *      feed's site_url HTML — most modern sites declare their icon.
 *   2. Fall back to `/favicon.ico` on the site origin.
 *   3. Save the binary under the local disk; persist the relative
 *      path on the feed so the route can serve it on next page load.
 *
 * The fetcher is best-effort — favicon fetch failures are logged and
 * silently dropped so they never break a feed refresh.
 */
class FaviconFetcher
{
    private const MAX_BYTES = 200 * 1024;

    private const CACHE_DIR = 'rss-favicons';

    private SafeUrl $safeUrl;

    public function __construct(?SafeUrl $safeUrl = null)
    {
        $this->safeUrl = $safeUrl ?? new SafeUrl;
    }

    public function fetch(string $siteUrl): ?string
    {
        $siteUrl = trim($siteUrl);
        if ($siteUrl === '' || ! preg_match('#^https?://#i', $siteUrl)) {
            return null;
        }
        $parts = parse_url($siteUrl);
        if (! $parts || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }
        $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
        if (! $this->safeUrl->isSafe($origin.'/')) {
            return null;
        }

        $html = $this->tryFetchHtml($origin.'/');

        // Try the discovered icon first (absolutized against the origin), then
        // fall back to the well-known /favicon.ico if it is missing, unsafe,
        // or fails to download.
        $candidates = [];
        $discovered = $this->discover($html);
        if ($discovered !== null && ($abs = $this->absolutize($origin, $discovered)) !== null) {
            $candidates[] = $abs;
        }
        $candidates[] = $origin.'/favicon.ico';

        $bytes = null;
        $iconUrl = null;
        foreach (array_unique($candidates) as $candidate) {
            if (! $this->safeUrl->isSafe($candidate)) {
                continue;
            }
            $bytes = $this->download($candidate);
            if ($bytes !== null) {
                $iconUrl = $candidate;
                break;
            }
        }
        if ($bytes === null || $iconUrl === null) {
            return null;
        }

        $ext = $this->extension($iconUrl, $bytes);
        $path = self::CACHE_DIR.'/'.hash('sha256', $origin).'.'.$ext;
        Storage::disk('local')->put($path, $bytes);

        return $path;
    }

    private function tryFetchHtml(string $url): ?string
    {
        try {
            $response = Http::timeout(8)->withOptions(['allow_redirects' => $this->safeUrl->allowRedirects(5)])->get($url);
        } catch (Throwable) {
            return null;
        }
        if (! $response->successful()) {
            return null;
        }
        $ct = strtolower((string) $response->header('Content-Type'));
        if ($ct !== '' && ! str_contains($ct, 'html')) {
            return null;
        }

        return $response->body();
    }

    private function discover(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }
        if (! preg_match_all('/<link\b[^>]*>/i', $html, $matches)) {
            return null;
        }

        $best = null;
        $bestSize = PHP_INT_MAX;
        foreach ($matches[0] as $tag) {
            if (! preg_match('/\brel\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i', $tag, $relMatch)) {
                continue;
            }
            $rel = strtolower($relMatch[1] !== '' ? $relMatch[1] : ($relMatch[2] ?? ''));
            if (! str_contains($rel, 'icon')) {
                continue;
            }
            if (! preg_match('/\bhref\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i', $tag, $hrefMatch)) {
                continue;
            }
            $href = $hrefMatch[1] !== '' ? $hrefMatch[1] : ($hrefMatch[2] ?? '');
            if ($href === '') {
                continue;
            }
            // Prefer apple-touch-icon for retina displays, fall back to icon.
            $size = str_contains($rel, 'apple-touch-icon') ? 0 : 1;
            if ($size < $bestSize) {
                $best = $href;
                $bestSize = $size;
            }
        }

        return $best;
    }

    private function download(string $url): ?string
    {
        try {
            $response = Http::timeout(8)->withOptions(['allow_redirects' => $this->safeUrl->allowRedirects(5)])->get($url);
        } catch (Throwable) {
            return null;
        }
        if (! $response->successful()) {
            return null;
        }
        $body = $response->body();
        if (strlen($body) === 0 || strlen($body) > self::MAX_BYTES) {
            return null;
        }

        return $body;
    }

    /**
     * Resolve an icon href (absolute, protocol-relative, root-relative, or
     * path-relative) against the site origin. Returns null when it cannot be
     * turned into an absolute http(s) URL.
     */
    private function absolutize(string $origin, string $href): ?string
    {
        $href = trim($href);
        if ($href === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }
        if (str_starts_with($href, '//')) {
            $scheme = parse_url($origin, PHP_URL_SCHEME) ?: 'https';

            return $scheme.':'.$href;
        }
        if (str_starts_with($href, '/')) {
            return $origin.$href;
        }

        return $origin.'/'.$href;
    }

    private function extension(string $url, string $bytes): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (in_array($ext, ['ico', 'png', 'jpg', 'jpeg', 'gif', 'svg'], true)) {
            return $ext === 'jpeg' ? 'jpg' : $ext;
        }
        if (str_starts_with($bytes, "\x89PNG")) {
            return 'png';
        }
        if (str_starts_with($bytes, "\xFF\xD8\xFF")) {
            return 'jpg';
        }
        if (str_starts_with($bytes, 'GIF8')) {
            return 'gif';
        }
        if (str_starts_with($bytes, '<?xml') || str_starts_with($bytes, '<svg')) {
            return 'svg';
        }

        return 'ico';
    }
}
