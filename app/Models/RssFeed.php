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
        'enabled',
        'refresh_interval_minutes',
        'folder',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'last_fetched_at' => 'datetime',
            'last_success_at' => 'datetime',
            'refresh_interval_minutes' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(RssItem::class, 'feed_id');
    }

    /** Feeds owned by the given user. */
    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /** Feeds due for refresh: enabled and last_fetched_at older than the interval. */
    public function scopeDueForRefresh(Builder $query): Builder
    {
        return $query->where('enabled', true)
            ->where(function (Builder $q) {
                $q->whereNull('last_fetched_at')
                    ->orWhereRaw('last_fetched_at <= DATE_SUB(NOW(), INTERVAL refresh_interval_minutes MINUTE)');
            });
    }
}
