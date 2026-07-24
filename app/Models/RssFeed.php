<?php

namespace App\Models;

use Database\Factories\RssFeedFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A user-owned RSS/Atom feed. The module boundary treats this row as a pointer
 * to a remote XML resource plus its HTTP cache state (ETag, Last-Modified).
 * Side effects on other modules (notifications, bookmarks, tags) are NOT done
 * from this model — the automation engine reacts to emitted events instead.
 */
class RssFeed extends Model
{
    /** @use HasFactory<RssFeedFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'url',
        'site_url',
        'description',
        'favicon_path',
        'etag',
        'last_modified',
        'last_fetched_at',
        'last_success_at',
        'last_error',
        'last_http_status',
        'last_response_time_ms',
        'consecutive_failures',
        'enabled',
        'muted_at',
        'refresh_interval_minutes',
        'folder',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'muted_at' => 'datetime',
            'last_fetched_at' => 'datetime',
            'last_success_at' => 'datetime',
            'refresh_interval_minutes' => 'integer',
            'sort_order' => 'integer',
            'last_http_status' => 'integer',
            'last_response_time_ms' => 'integer',
            'consecutive_failures' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Keep url_hash in lockstep with url so the (user_id, url_hash) unique
        // index dedupes subscriptions regardless of how the feed was created.
        static::saving(function (RssFeed $feed) {
            $feed->url_hash = sha1((string) $feed->url);
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RssItem::class, 'feed_id');
    }

    public function refreshLogs(): HasMany
    {
        return $this->hasMany(RssRefreshLog::class, 'rss_feed_id');
    }

    /** Feeds owned by the given user. */
    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Refresh candidates: enabled and unmuted. This is the DB-cheap filter;
     * the exact per-feed interval gate (last_fetched_at older than
     * refresh_interval_minutes) is applied in PHP via isDueForRefresh(),
     * because a column-driven interval comparison is not portable across
     * the MySQL (app) and SQLite (test) grammars.
     */
    public function scopeDueForRefresh(Builder $query): Builder
    {
        return $query->where('enabled', true)->whereNull('muted_at');
    }

    /**
     * Whether this feed's refresh interval has elapsed since the last fetch.
     * A never-fetched feed is always due.
     */
    public function isDueForRefresh(): bool
    {
        if (! $this->enabled || $this->isMuted()) {
            return false;
        }
        if ($this->last_fetched_at === null) {
            return true;
        }

        $interval = $this->refresh_interval_minutes ?: 60;

        return $this->last_fetched_at->copy()->addMinutes($interval)->lessThanOrEqualTo(now());
    }

    /** Feeds that are currently muted. */
    public function scopeMuted(Builder $query): Builder
    {
        return $query->whereNotNull('muted_at');
    }

    /** Feeds that are not muted. */
    public function scopeUnmuted(Builder $query): Builder
    {
        return $query->whereNull('muted_at');
    }

    public function isMuted(): bool
    {
        return $this->muted_at !== null;
    }

    public function mute(): bool
    {
        if ($this->isMuted()) {
            return false;
        }
        $this->muted_at = now();
        $saved = $this->save();
        if ($saved) {
            $this->syncMutedItemsSearchable();
        }

        return $saved;
    }

    public function unmute(): bool
    {
        if (! $this->isMuted()) {
            return false;
        }
        $this->muted_at = null;
        $saved = $this->save();
        if ($saved) {
            $this->syncMutedItemsSearchable();
        }

        return $saved;
    }

    /**
     * Re-sync the Scout search index for every item on this feed when
     * the muted state changes. shouldBeSearchable() keys off
     * feed->muted_at, but the Scout observer only fires on item save —
     * so a feed muted long after the items were indexed keeps its
     * items visible in /search until the next item save. chunkById keeps
     * the in-flight index writes bounded for big feeds.
     */
    public function syncMutedItemsSearchable(): void
    {
        $this->items()->orderBy('id')->chunkById(200, function ($items) {
            foreach ($items as $item) {
                // Point the item at this in-memory feed so shouldBeSearchable()
                // reads the just-saved muted state without an N+1 feed lookup.
                $item->setRelation('feed', $this);
                if ($item->shouldBeSearchable()) {
                    $item->searchable();
                } else {
                    $item->unsearchable();
                }
            }
        });
    }

    /**
     * Propagate a feed title change to the denormalized feed_title on its
     * items (used by search) and re-index them so search reflects the rename.
     */
    public function syncItemsFeedTitle(): void
    {
        $this->items()->update(['feed_title' => $this->title]);

        $this->items()->orderBy('id')->chunkById(200, function ($items) {
            foreach ($items as $item) {
                $item->setRelation('feed', $this);
                if ($item->shouldBeSearchable()) {
                    $item->searchable();
                }
            }
        });
    }
}
