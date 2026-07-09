<?php

namespace App\Jobs;

use App\Jobs\Rss\AdoptBookmarkFeed;
use App\Models\Bookmark;
use App\Services\Http\SafeUrl;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Browsershot\Browsershot;
use Throwable;

/**
 * Health-checks and hydrates a bookmark: validates the URL responds (2xx/3xx,
 * else marked dead), downloads the site favicon, and — when enabled — captures
 * a page screenshot. Every outbound fetch (page, favicon, screenshot) is
 * SafeUrl-guarded so a bookmark URL can't be pointed at the private network
 * or the cloud metadata endpoint.
 */
class ProcessBookmark implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $bookmarkId) {}

    public function handle(SafeUrl $safeUrl): void
    {
        $bookmark = Bookmark::find($this->bookmarkId);
        if (! $bookmark) {
            return;
        }

        // Only ever fetch public http(s) URLs from the server — reject other
        // schemes and any host that resolves into a private/reserved range.
        if (! preg_match('#^https?://#i', $bookmark->url) || ! $safeUrl->isSafe($bookmark->url)) {
            $bookmark->update(['status' => Bookmark::STATUS_DEAD, 'last_checked_at' => now()]);

            return;
        }

        $response = null;
        try {
            $response = Http::timeout(config('bookmarks.http_timeout', 8))
                ->withOptions(['allow_redirects' => $safeUrl->allowRedirects(5)])
                ->get($bookmark->url);
        } catch (Throwable) {
            // network failure / DNS / timeout / blocked redirect → dead
        }

        if (! $response || ! ($response->successful() || $response->redirect())) {
            $bookmark->update(['status' => Bookmark::STATUS_DEAD, 'last_checked_at' => now()]);

            return;
        }

        $attributes = ['status' => Bookmark::STATUS_ALIVE, 'last_checked_at' => now()];

        if ($iconPath = $this->fetchFavicon($bookmark, $response->body(), $safeUrl)) {
            $attributes['icon_path'] = $iconPath;
        }
        $adoptFeed = false;
        if ($feed = $this->detectFeed($bookmark->url, $response->body())) {
            $attributes['feed_url'] = $feed;
            $adoptFeed = true;
        }
        if ($shotPath = $this->screenshot($bookmark, $safeUrl)) {
            $attributes['screenshot_path'] = $shotPath;
        }

        $bookmark->update($attributes);

        // Dispatch only after feed_url is persisted (and after any surrounding
        // transaction commits) — the adopt job reads feed_url from the DB, so
        // dispatching earlier races the write and silently drops the feed.
        if ($adoptFeed) {
            AdoptBookmarkFeed::dispatch($bookmark->id)->afterCommit();
        }
    }

    /** Download the site favicon and store it; returns the stored path or null. */
    private function fetchFavicon(Bookmark $bookmark, string $html, SafeUrl $safeUrl): ?string
    {
        $candidate = $this->faviconUrl($bookmark->url, $html);
        // The candidate comes from the page's own <link> tag, so re-guard it —
        // a page can point its favicon at an internal host.
        if (! $candidate || ! $safeUrl->isSafe($candidate)) {
            return null;
        }

        try {
            $res = Http::timeout(config('bookmarks.http_timeout', 8))
                ->withOptions(['allow_redirects' => $safeUrl->allowRedirects(5)])
                ->get($candidate);
        } catch (Throwable) {
            return null;
        }

        $type = strtolower((string) $res->header('Content-Type'));
        if (! $res->successful() || ! str_contains($type, 'image')) {
            return null;
        }

        $ext = match (true) {
            str_contains($type, 'png') => 'png',
            str_contains($type, 'svg') => 'svg',
            str_contains($type, 'jpeg'), str_contains($type, 'jpg') => 'jpg',
            str_contains($type, 'gif') => 'gif',
            default => 'ico',
        };
        $path = "bookmarks/{$bookmark->owner_id}/{$bookmark->id}/icon.{$ext}";
        Storage::disk(config('filemanager.disk'))->put($path, $res->body());

        return $path;
    }

    /** Resolve the best favicon URL: <link rel=icon> if present, else /favicon.ico. */
    private function faviconUrl(string $pageUrl, string $html): ?string
    {
        $href = null;
        if (preg_match_all('/<link\b[^>]*>/i', $html, $tags)) {
            foreach ($tags[0] as $tag) {
                if (preg_match('/rel=["\'][^"\']*icon[^"\']*["\']/i', $tag)
                    && preg_match('/href=["\']([^"\']+)["\']/i', $tag, $m)) {
                    $href = trim($m[1]);
                    break;
                }
            }
        }

        return $href ? $this->resolveUrl($pageUrl, $href) : ($this->origin($pageUrl) ? $this->origin($pageUrl).'/favicon.ico' : null);
    }

    /** Find an RSS/Atom feed declared in the page <head>, if any. */
    private function detectFeed(string $pageUrl, string $html): ?string
    {
        if (preg_match_all('/<link\b[^>]*>/i', $html, $tags)) {
            foreach ($tags[0] as $tag) {
                if (preg_match('#type=["\']application/(?:rss|atom)\+xml["\']#i', $tag)
                    && preg_match('/href=["\']([^"\']+)["\']/i', $tag, $m)) {
                    return $this->resolveUrl($pageUrl, trim($m[1]));
                }
            }
        }

        return null;
    }

    /** Scheme+host(+port) origin of a URL, or null. */
    private function origin(string $url): ?string
    {
        $parts = parse_url($url);
        if (empty($parts['host'])) {
            return null;
        }

        return ($parts['scheme'] ?? 'https').'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }

    /** Resolve a possibly-relative href against the page URL. */
    private function resolveUrl(string $pageUrl, string $href): ?string
    {
        if (preg_match('#^https?://#i', $href)) {
            return $href;
        }
        $origin = $this->origin($pageUrl);
        if (! $origin) {
            return null;
        }
        if (str_starts_with($href, '//')) {
            return (parse_url($pageUrl, PHP_URL_SCHEME) ?: 'https').':'.$href;
        }

        return str_starts_with($href, '/') ? $origin.$href : $origin.'/'.ltrim($href, '/');
    }

    /**
     * Capture a screenshot via spatie/browsershot when enabled and installed.
     * Returns the stored path or null (feature off, package missing, or failure).
     */
    private function screenshot(Bookmark $bookmark, SafeUrl $safeUrl): ?string
    {
        if (! config('bookmarks.screenshots.enabled') || ! class_exists(Browsershot::class)) {
            return null;
        }
        // Headless Chrome follows its own redirects/JS navigations with no hook
        // to guard each hop, so at minimum refuse a URL that already resolves
        // into a private/reserved range before launching the browser.
        if (! $safeUrl->isSafe($bookmark->url)) {
            return null;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'bmshot_').'.png';
        try {
            $shot = Browsershot::url($bookmark->url)
                ->windowSize(config('bookmarks.screenshots.width', 1366), config('bookmarks.screenshots.height', 768))
                ->setScreenshotType('png')
                ->timeout(config('bookmarks.http_timeout', 8) + 12);

            // Chromium won't start as root / in a container without this.
            if (config('bookmarks.screenshots.no_sandbox', true)) {
                $shot->noSandbox();
            }
            if ($node = config('bookmarks.screenshots.node_binary')) {
                $shot->setNodeBinary($node);
            }
            if ($chrome = config('bookmarks.screenshots.chrome_path')) {
                $shot->setChromePath($chrome);
            }
            $shot->save($tmp);

            $path = "bookmarks/{$bookmark->owner_id}/{$bookmark->id}/shot.png";
            Storage::disk(config('filemanager.disk'))->put($path, file_get_contents($tmp));

            return $path;
        } catch (Throwable $e) {
            // Screenshots are best-effort, but a silent failure is undiagnosable
            // (missing Chromium, sandbox, timeout) — log it so operators can fix.
            \Illuminate\Support\Facades\Log::warning('bookmark.screenshot.failed', [
                'bookmark' => $bookmark->id,
                'reason' => $e->getMessage(),
            ]);

            return null;
        } finally {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }
}
