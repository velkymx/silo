<?php

namespace App\Models;

use Database\Factories\RssItemFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Scout\Searchable;

/**
 * A single parsed entry from an RSS/Atom feed. Immutable except for the user
 * bookkeeping flags (is_read, is_starred); the ingestion layer enforces that
 * by only ever calling create() and the narrow toggle methods below.
 */
class RssItem extends Model
{
    /** @use HasFactory<RssItemFactory> */
    use HasFactory, Searchable;

    protected $fillable = [
        'feed_id',
        'user_id',
        'guid',
        'title',
        'content',
        'excerpt',
        'author',
        'categories',
        'image_url',
        'url',
        'published_at',
        'is_read',
        'is_starred',
        'read_at',
        'starred_at',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
            'is_starred' => 'boolean',
            'published_at' => 'datetime',
            'read_at' => 'datetime',
            'starred_at' => 'datetime',
            'categories' => 'array',
        ];
    }

    /**
     * Columns indexed in the search index. Kept narrow — title + excerpt are
     * enough to surface a useful hit; full HTML content is too noisy.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'author' => $this->author,
            'url' => $this->url,
        ];
    }

    public function feed(): BelongsTo
    {
        return $this->belongsTo(RssFeed::class, 'feed_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->where('is_read', false);
    }

    public function scopeStarred(Builder $query): Builder
    {
        return $query->where('is_starred', true);
    }

    public function scopeForFeed(Builder $query, int $feedId): Builder
    {
        return $query->where('feed_id', $feedId);
    }

    public function markRead(): void
    {
        if ($this->is_read) {
            return;
        }
        $this->is_read = true;
        $this->read_at = now();
        $this->save();
    }

    public function markUnread(): void
    {
        if (! $this->is_read) {
            return;
        }
        $this->is_read = false;
        $this->read_at = null;
        $this->save();
    }

    public function toggleStar(): bool
    {
        $this->is_starred = ! $this->is_starred;
        $this->starred_at = $this->is_starred ? now() : null;
        $this->save();

        return $this->is_starred;
    }
}
