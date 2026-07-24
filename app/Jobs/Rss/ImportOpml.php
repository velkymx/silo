<?php

namespace App\Jobs\Rss;

use App\Models\RssFeed;
use App\Services\Rss\OpmlParser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Bulk-import feeds from an OPML 2.0 file. Idempotent on (user_id, url)
 * — running the import twice does not duplicate a feed. The OPML payload
 * travels through the queue as a string (not as a file handle) so the
 * job can run on any worker without the original upload.
 *
 * The first import kicks off a per-feed RefreshFeed so the inbox is
 * not empty on next page load.
 */
class ImportOpml implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    /** Most feeds a single OPML import will create (fan-out guard). */
    public const MAX_ENTRIES = 500;

    /** Hard ceiling on how many feeds one user may accumulate. */
    public const MAX_FEEDS_PER_USER = 2000;

    public function __construct(public int $userId, public string $opml) {}

    public function handle(OpmlParser $parser): void
    {
        $entries = $parser->parse($this->opml);
        $parsedCount = count($entries);
        if ($parsedCount > self::MAX_ENTRIES) {
            $entries = array_slice($entries, 0, self::MAX_ENTRIES);
            Log::warning('rss.opml.truncated', [
                'user' => $this->userId,
                'parsed' => $parsedCount,
                'cap' => self::MAX_ENTRIES,
            ]);
        }

        $feedCount = RssFeed::where('user_id', $this->userId)->count();
        $created = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($entries as $entry) {
            if ($feedCount >= self::MAX_FEEDS_PER_USER) {
                Log::warning('rss.opml.user_feed_cap_reached', [
                    'user' => $this->userId,
                    'cap' => self::MAX_FEEDS_PER_USER,
                ]);
                break;
            }

            $url = $entry['url'];
            if (! preg_match('#^https?://#i', $url)) {
                $failed++;

                continue;
            }

            $existing = RssFeed::where('user_id', $this->userId)
                ->where('url', $url)
                ->first();
            if ($existing) {
                $skipped++;

                continue;
            }

            try {
                $feed = RssFeed::create([
                    'user_id' => $this->userId,
                    'title' => $entry['title'],
                    'url' => $url,
                    'folder' => $entry['folder'],
                    'enabled' => true,
                    'refresh_interval_minutes' => 60,
                ]);
                RefreshFeed::dispatch($feed->id);
                $created++;
                $feedCount++;
            } catch (Throwable $e) {
                Log::warning('rss.opml.feed_create_failed', [
                    'user' => $this->userId,
                    'url' => $url,
                    'reason' => $e->getMessage(),
                ]);
                $failed++;
            }
        }

        Log::info('rss.opml.imported', [
            'user' => $this->userId,
            'parsed' => $parsedCount,
            'created' => $created,
            'skipped' => $skipped,
            'failed' => $failed,
        ]);
    }
}
