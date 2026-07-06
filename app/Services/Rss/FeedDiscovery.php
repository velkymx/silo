<?php

namespace App\Services\Rss;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Lightweight auto-discovery for RSS/Atom feeds from a page URL.
 *
 * Fetches the HTML body once, scans for <link rel="alternate"> with an
 * RSS/Atom MIME type, and returns the first match. Preferring the more
 * specific MIME type first (rss, then atom) keeps the behavior
 * deterministic when a page advertises both.
 *
 * Returns null on any failure (network, non-HTML, no link found) so the
 * caller can fall through to its existing behavior without branching on
 * exception types.
 */
class FeedDiscovery
{
    private const FEED_MIME_TYPES = [
        'application/rss+xml',
        'application/atom+xml',
        'application/xml',
        'text/xml',
    ];

    private SafeUrl $safeUrl;

    public function __construct(?SafeUrl $safeUrl = null)
    {
        $this->safeUrl = $safeUrl ?? new SafeUrl;
    }

    public function discover(string $pageUrl): ?DiscoveredFeed
    {
        if (! preg_match('#^https?://#i', $pageUrl) || ! $this->safeUrl->isSafe($pageUrl)) {
            return null;
        }

        try {
            $response = Http::withHeaders(['User-Agent' => 'Silo-RSS-Discovery/1.0'])
                ->timeout(10)
                ->withOptions(['allow_redirects' => $this->safeUrl->allowRedirects(5)])
                ->get($pageUrl);
        } catch (Throwable) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $contentType = (string) $response->header('Content-Type');
        if ($contentType !== '' && ! str_contains(strtolower($contentType), 'html')) {
            return null;
        }

        $html = $response->body();
        if ($html === '' || ! str_contains(strtolower($html), '<link')) {
            return null;
        }

        $matches = [];
        // Match <link ... rel="alternate" ... type="..." ... href="...">
        preg_match_all(
            '/<link\b[^>]*\bhref\s*=\s*(?:"([^"]*)"|\'([^\']*)\')[^>]*>/i',
            $html,
            $matches,
        );

        if (! $matches[0]) {
            return null;
        }

        $candidates = [];
        foreach ($matches[0] as $i => $tag) {
            $href = $matches[1][$i] !== '' ? $matches[1][$i] : ($matches[2][$i] ?? '');
            if ($href === '') {
                continue;
            }

            if (! preg_match('/\brel\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i', $tag, $relMatch)) {
                continue;
            }
            $rel = strtolower($relMatch[1] !== '' ? $relMatch[1] : ($relMatch[2] ?? ''));
            if (str_contains($rel, 'alternate') === false) {
                continue;
            }

            $type = '';
            if (preg_match('/\btype\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i', $tag, $typeMatch)) {
                $type = strtolower(trim($typeMatch[1] !== '' ? $typeMatch[1] : ($typeMatch[2] ?? '')));
            }

            $title = null;
            if (preg_match('/\btitle\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i', $tag, $titleMatch)) {
                $title = trim($titleMatch[1] !== '' ? $titleMatch[1] : ($titleMatch[2] ?? ''));
                if ($title === '') {
                    $title = null;
                }
            }

            $candidates[] = ['href' => $href, 'type' => $type, 'title' => $title];
        }

        if (! $candidates) {
            return null;
        }

        // Prefer known feed MIME types in priority order; fall back to
        // the first alternate if none of the explicit types match.
        foreach (self::FEED_MIME_TYPES as $mime) {
            foreach ($candidates as $c) {
                if ($c['type'] === $mime) {
                    return $this->resolve($pageUrl, $c['href'], $c['title']);
                }
            }
        }

        return $this->resolve($pageUrl, $candidates[0]['href'], $candidates[0]['title']);
    }

    private function resolve(string $base, string $href, ?string $title): ?DiscoveredFeed
    {
        $resolved = $this->absolutize($base, $href);
        if ($resolved === null) {
            return null;
        }

        return new DiscoveredFeed($resolved, $title);
    }

    private function absolutize(string $base, string $href): ?string
    {
        $href = trim($href);
        if ($href === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }
        if (str_starts_with($href, '//')) {
            $scheme = parse_url($base, PHP_URL_SCHEME) ?: 'https';

            return $scheme.'://'.ltrim($href, '/');
        }

        $parts = parse_url($base);
        if (! $parts || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }
        $origin = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');

        if (str_starts_with($href, '/')) {
            return $origin.$href;
        }

        $basePath = $parts['path'] ?? '/';
        $dir = str_contains($basePath, '/') ? substr($basePath, 0, strrpos($basePath, '/') + 1) : '/';

        return $origin.$dir.$href;
    }
}
