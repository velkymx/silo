<?php

namespace App\Jobs;

use App\Jobs\Rss\AdoptBookmarkFeed;
use App\Models\Bookmark;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Browsershot\Browsershot;
use Throwable;

/**
 * Health-checks and hydrates a bookmark: validates the URL responds (2xx/3xx,
 * else marked dead), downloads the site favicon, and — when enabled — captures
 * a page screenshot. SSRF surface is limited to http(s) with a capped timeout.
 */
class ProcessBookmark implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $bookmarkId) {}

    public function handle(): void
    {
        $bookmark = Bookmark::find($this->bookmarkId);
        if (! $bookmark) {
            return;
        }

        // Only ever fetch http(s) URLs from the server.
        if (! preg_match('#^https?://#i', $bookmark->url)) {
            $bookmark->update(['status' => Bookmark::STATUS_DEAD, 'last_checked_at' => now()]);

            return;
        }

        $response = null;
        try {
            $response = Http::timeout(config('bookmarks.http_timeout', 8))
                ->withOptions(['allow_redirects' => ['max' => 5]])
                ->get($bookmark->url);
        } catch (Throwable) {
            // network failure / DNS / timeout → dead
        }

        if (! $response || ! ($response->successful() || $response->redirect())) {
            $bookmark->update(['status' => Bookmark::STATUS_DEAD, 'last_checked_at' => now()]);

            return;
        }

        $attributes = ['status' => Bookmark::STATUS_ALIVE, 'last_checked_at' => now()];

        if ($iconPath = $this->fetchFavicon($bookmark, $response->body())) {
            $attributes['icon_path'] = $iconPath;
        }
        if ($feed = $this->detectFeed($bookmark->url, $response->body())) {
            $attributes['feed_url'] = $feed;
            AdoptBookmarkFeed::dispatch($bookmark->id);
        }
        if ($shotPath = $this->screenshot($bookmark)) {
            $attributes['screenshot_path'] = $shotPath;
        }

        $bookmark->update($attributes);
    }

    /** Download the site favicon and store it; returns the stored path or null. */
    private function fetchFavicon(Bookmark $bookmark, string $html): ?string
    {
        $candidate = $this->faviconUrl($bookmark->url, $html);
        if (! $candidate) {
            return null;
        }

        try {
            $res = Http::timeout(config('bookmarks.http_timeout', 8))->get($candidate);
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
    private function screenshot(Bookmark $bookmark): ?string
    {
        if (! config('bookmarks.screenshots.enabled') || ! class_exists(Browsershot::class)) {
            return null;
        }

        $tmp = tempnam(sys_get_temp_dir(), 'bmshot_').'.png';
        try {
            $shot = Browsershot::url($bookmark->url)
                ->windowSize(config('bookmarks.screenshots.width', 1366), config('bookmarks.screenshots.height', 768))
                ->setScreenshotType('png')
                ->timeout(config('bookmarks.http_timeout', 8) + 12);

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
        } catch (Throwable) {
            return null;
        } finally {
            if (is_file($tmp)) {
                @unlink($tmp);
            }
        }
    }
}
