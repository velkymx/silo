<?php

namespace App\Jobs\Rss;

use App\Models\Bookmark;
use App\Models\RssFeed;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * The bookmark hydration job (ProcessBookmark) detects a page-level feed
 * URL and stashes it on `bookmarks.feed_url`. This sibling job adopts
 * that feed into the user's RSS reader — exactly once per (user, url).
 *
 * Bookmarks already imply the user cares about the source, so a successful
 * hydration is a strong "subscribe me" signal. The adoption is silent
 * (no UI prompt) and respects existing subscriptions.
 */
class AdoptBookmarkFeed implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 30;

    /** @param  ?int  $forUserId  subscribe this user instead of the bookmark's owner (explicit Subscribe button) */
    public function __construct(public int $bookmarkId, public ?int $forUserId = null) {}

    public function handle(): void
    {
        $bookmark = Bookmark::find($this->bookmarkId);
        if (! $bookmark) {
            return;
        }
        $userId = $this->forUserId ?? $bookmark->owner_id;
        $feedUrl = trim((string) $bookmark->feed_url);
        if ($feedUrl === '' || ! preg_match('#^https?://#i', $feedUrl)) {
            return;
        }

        // Idempotency: same subscriber + same URL = at most one feed.
        $existing = RssFeed::where('user_id', $userId)
            ->where('url', $feedUrl)
            ->first();
        if ($existing) {
            return;
        }

        $feed = RssFeed::create([
            'user_id' => $userId,
            'title' => $bookmark->title ?: $feedUrl,
            'url' => $feedUrl,
            'site_url' => $bookmark->url,
            'description' => $bookmark->description,
            'folder' => $bookmark->category,
            'enabled' => true,
            'refresh_interval_minutes' => 60,
        ]);

        // First fetch straight away so the inbox isn't empty tomorrow.
        RefreshFeed::dispatch($feed->id);

        Log::info('rss.feed.adopted_from_bookmark', [
            'bookmark' => $bookmark->id,
            'feed' => $feed->id,
            'user' => $userId,
        ]);
    }
}
