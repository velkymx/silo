<?php

namespace App\Services\Rss;

/**
 * SSRF guard for every server-side fetch the RSS module performs (feed
 * refresh, feed discovery, favicon download, OPML-by-URL import).
 *
 * User- and feed-supplied URLs must never let the app reach into the
 * private network or the cloud metadata endpoint. A URL is safe only when:
 *   - its scheme is http or https, and
 *   - every A/AAAA record its host resolves to is a public, non-reserved
 *     address (blocks 127/8, 10/8, 172.16/12, 192.168/16, 169.254/16,
 *     ::1, fc00::/7, and other IANA-reserved ranges).
 *
 * Redirects are guarded per hop (`allowRedirects()`) so a public URL cannot
 * 302 into an internal one. This is not a defense against active DNS
 * rebinding (the OS reconnects after our lookup), but it closes the common
 * static and redirect-based SSRF vectors.
 */
class SafeUrl
{
    public function isSafe(string $url): bool
    {
        $parts = parse_url(trim($url));
        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return false;
        }
        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        $host = trim($parts['host'], '[]');

        // Literal IP host: classify directly, no DNS.
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return $this->ipIsPublic($host);
        }

        $ips = $this->resolve($host);
        // Unresolvable host: nothing to connect to, so nothing to protect —
        // let the HTTP layer fail naturally rather than block here.
        if ($ips === []) {
            return true;
        }

        foreach ($ips as $ip) {
            if (! $this->ipIsPublic($ip)) {
                return false;
            }
        }

        return true;
    }

    public function assert(string $url): void
    {
        if (! $this->isSafe($url)) {
            throw new UnsafeUrlException("Blocked unsafe URL: {$url}");
        }
    }

    /**
     * Guzzle allow_redirects config that re-checks every redirect target.
     *
     * @return array<string, mixed>
     */
    public function allowRedirects(int $max = 5): array
    {
        return [
            'max' => $max,
            'strict' => true,
            'referer' => false,
            'protocols' => ['http', 'https'],
            'on_redirect' => function ($request, $response, $uri): void {
                $this->assert((string) $uri);
            },
        ];
    }

    private function ipIsPublic(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

    /**
     * @return array<int, string>
     */
    private function resolve(string $host): array
    {
        $ips = [];

        $v4 = @gethostbynamel($host);
        if (is_array($v4)) {
            $ips = $v4;
        }

        $records = @dns_get_record($host, DNS_AAAA);
        if (is_array($records)) {
            foreach ($records as $record) {
                if (! empty($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        return $ips;
    }
}
