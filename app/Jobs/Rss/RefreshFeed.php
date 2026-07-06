<?php

namespace App\Jobs\Rss;

use App\Automation\EventDispatcher;
use App\Models\RssFeed;
use App\Models\RssItem;
use App\Models\RssRefreshLog;
use App\Services\Audit;
use App\Services\Rss\FaviconFetcher;
use App\Services\Rss\HtmlSanitizer;
use App\Services\Rss\Parser;
use App\Services\Rss\SafeUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fetches one feed with HTTP cache headers, parses the body, persists new
 * items (GUID dedupe), and dispatches one rss.item.created event per new
 * row through the automation engine. The job is idempotent: re-running it
 * over an already-ingested feed is a no-op aside from refreshing cache state.
 */
class RefreshFeed implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public int $timeout = 60;

    public function __construct(public int $feedId) {}

    public function handle(Parser $parser, EventDispatcher $events, FaviconFetcher $favicons, HtmlSanitizer $sanitizer, SafeUrl $safeUrl): void
    {
        $feed = RssFeed::find($this->feedId);
        if (! $feed || ! $feed->enabled || $feed->isMuted()) {
            return;
        }

        $startedAt = now();

        if (! preg_match('#^https?://#i', $feed->url)) {
            $this->logAttempt($feed, $startedAt, null, null, RssRefreshLog::OUTCOME_CONNECTION, 0, 'URL must be http(s)');
            $feed->update([
                'last_error' => 'URL must be http(s)',
                'last_http_status' => null,
                'consecutive_failures' => $feed->consecutive_failures + 1,
            ]);

            return;
        }

        if (! $safeUrl->isSafe($feed->url)) {
            $feed->update([
                'last_error' => 'Blocked: URL resolves to a private or reserved address',
                'last_http_status' => null,
                'consecutive_failures' => $feed->consecutive_failures + 1,
            ]);

            return;
        }

        $headers = [
            'User-Agent' => config('app.name').'/RSS-Reader (+'.config('app.url').')',
            'Accept' => 'application/rss+xml, application/atom+xml, application/xml;q=0.9, */*;q=0.8',
        ];
        if ($feed->etag) {
            $headers['If-None-Match'] = $feed->etag;
        }
        if ($feed->last_modified) {
            $headers['If-Modified-Since'] = $feed->last_modified;
        }

        $start = microtime(true);
        try {
            $response = Http::withHeaders($headers)
                ->timeout(20)
                ->withOptions(['allow_redirects' => $safeUrl->allowRedirects(5)])
                ->get($feed->url);
        } catch (ConnectionException $e) {
            $feed->update([
                'last_error' => 'connection: '.$e->getMessage(),
                'last_http_status' => null,
                'last_response_time_ms' => (int) round((microtime(true) - $start) * 1000),
                'consecutive_failures' => $feed->consecutive_failures + 1,
            ]);

            return;
        } catch (Throwable $e) {
            $feed->update([
                'last_error' => substr($e->getMessage(), 0, 480),
                'last_http_status' => null,
                'last_response_time_ms' => (int) round((microtime(true) - $start) * 1000),
                'consecutive_failures' => $feed->consecutive_failures + 1,
            ]);
            throw $e;
        }
        $elapsedMs = (int) round((microtime(true) - $start) * 1000);

        $now = now();
        if ($response->status() === 304) {
            $feed->update([
                'last_fetched_at' => $now,
                'last_success_at' => $now,
                'last_error' => null,
                'last_http_status' => 304,
                'last_response_time_ms' => $elapsedMs,
                'consecutive_failures' => 0,
            ]);
            $events->dispatch('rss.feed.fetched', $feed->user_id, [
                'feed_id' => $feed->id,
                'new_count' => 0,
                'not_modified' => true,
                'fetched_at' => $now->toIso8601String(),
            ], "rss.feed.fetched@1:source:{$feed->id}:".$now->timestamp);

            return;
        }

        if (! $response->successful()) {
            $feed->update([
                'last_fetched_at' => $now,
                'last_error' => 'HTTP '.$response->status(),
                'last_http_status' => $response->status(),
                'last_response_time_ms' => $elapsedMs,
                'consecutive_failures' => $feed->consecutive_failures + 1,
            ]);

            return;
        }

        $parsed = $parser->parse($response->body());

        $etag = $response->header('ETag');
        $lastModified = $response->header('Last-Modified');

        $attributes = [
            'last_fetched_at' => $now,
            'last_success_at' => $now,
            'last_error' => null,
            'last_http_status' => $response->status(),
            'last_response_time_ms' => $elapsedMs,
            'consecutive_failures' => 0,
            'etag' => is_string($etag) ? $etag : $feed->etag,
            'last_modified' => is_string($lastModified) ? $lastModified : $feed->last_modified,
        ];
        if (($feed->title === null || $feed->title === '') && ! empty($parsed['title'])) {
            $attributes['title'] = $parsed['title'];
        }
        if (! $feed->site_url && ! empty($parsed['site_url'])) {
            $attributes['site_url'] = $parsed['site_url'];
        }
        $feed->update($attributes);

        $existing = $feed->items()->pluck('guid')->all();
        $existingSet = array_flip($existing);
        $newCount = 0;

        foreach ($parsed['entries'] as $entry) {
            if (isset($existingSet[$entry['guid']])) {
                continue;
            }
            try {
                $item = RssItem::create([
                    'feed_id' => $feed->id,
                    'user_id' => $feed->user_id,
                    'guid' => $entry['guid'],
                    // Column is string(255); truncate so an oversize title/author
                    // never throws and drops the item on every refresh.
                    'title' => $entry['title'] !== '' ? mb_substr($entry['title'], 0, 255) : '(untitled)',
                    'content' => $sanitizer->clean($entry['content'] ?: null),
                    'excerpt' => $entry['excerpt'] ?: null,
                    'author' => $entry['author'] !== null ? mb_substr($entry['author'], 0, 255) : null,
                    'categories' => $entry['categories'] ?? null,
                    'image_url' => $entry['image_url'] ?? null,
                    // url is NOT NULL; fall back to '' when neither the entry nor
                    // the feed supplies one, rather than violating the constraint.
                    'url' => $entry['url'] !== '' ? $entry['url'] : ($feed->site_url ?? ''),
                    'published_at' => $entry['published_at'],
                ]);
            } catch (Throwable $e) {
                Log::warning('rss.item.create.skipped', ['feed' => $feed->id, 'guid' => $entry['guid'], 'reason' => $e->getMessage()]);

                continue;
            }
            $existingSet[$entry['guid']] = true;
            $newCount++;
            Audit::log('rss.item.create', null, ['item_id' => $item->id, 'feed_id' => $feed->id], userId: $feed->user_id, subjectName: $item->title);

            $events->dispatch('rss.item.created', $feed->user_id, [
                'item_id' => $item->id,
                'feed_id' => $feed->id,
                'title' => $item->title,
                'excerpt' => $item->excerpt,
                'author' => $item->author,
                'url' => $item->url,
                'feed_url' => $feed->url,
                'published_at' => optional($item->published_at)->toIso8601String(),
            ], "rss.item.created@1:item:{$item->id}");
        }

        $events->dispatch('rss.feed.fetched', $feed->user_id, [
            'feed_id' => $feed->id,
            'new_count' => $newCount,
            'not_modified' => false,
            'fetched_at' => $now->toIso8601String(),
        ], "rss.feed.fetched@1:source:{$feed->id}:".$now->timestamp);

        $this->logAttempt($feed, $startedAt, $now, $elapsedMs, RssRefreshLog::OUTCOME_SUCCESS, $newCount, null, $response->status());

        // Best-effort favicon fetch on the first successful pull — only
        // runs when the feed has no icon yet, and only when the parser
        // actually discovered a site_url from the channel metadata.
        if (empty($feed->favicon_path) && $feed->site_url) {
            try {
                $path = $favicons->fetch($feed->site_url);
                if ($path !== null) {
                    $feed->favicon_path = $path;
                    $feed->save();
                }
            } catch (Throwable $e) {
                Log::warning('rss.favicon.fetch_failed', [
                    'feed' => $feed->id,
                    'site' => $feed->site_url,
                    'reason' => $e->getMessage(),
                ]);
            }
        }
    }

    private function logAttempt(RssFeed $feed, \Illuminate\Support\Carbon $startedAt, ?\Illuminate\Support\Carbon $completedAt, ?int $ms, int $outcome, int $newItems, ?string $error, ?int $httpStatus = null): void
    {
        try {
            RssRefreshLog::create([
                'rss_feed_id' => $feed->id,
                'user_id' => $feed->user_id,
                'started_at' => $startedAt,
                'completed_at' => $completedAt,
                'http_status' => $httpStatus,
                'response_time_ms' => $ms,
                'outcome' => $outcome,
                'new_items_count' => $newItems,
                'error' => $error !== null ? substr($error, 0, 500) : null,
            ]);
        } catch (Throwable $e) {
            // The log is best-effort — never let a logging failure break a refresh.
            Log::warning('rss.refresh_log.write_failed', ['feed' => $feed->id, 'reason' => $e->getMessage()]);
        }
    }

    public function failed(Throwable $e): void
    {
        RssFeed::whereKey($this->feedId)->update(['last_error' => substr($e->getMessage(), 0, 480)]);
        Log::error('rss.refresh_feed.failed', ['feed' => $this->feedId, 'error' => $e->getMessage()]);
    }
}
