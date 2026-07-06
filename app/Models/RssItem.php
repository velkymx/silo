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
     * Columns indexed in the search index. Kept narrow — title + excerpt
     * are the primary signal; author and feed name are kept so the
     * search results page can group and display the right context.
     * Full HTML content is too noisy for the index.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'author' => $this->author,
            'feed_title' => $this->feed?->title,
            'url' => $this->url,
        ];
    }

    /**
     * Muted-feed items never enter the search index — a user who muted a
     * feed has explicitly opted out of seeing its content, so making it
     * searchable from the global search would bypass that intent.
     */
    public function shouldBeSearchable(): bool
    {
        return $this->feed !== null && $this->feed->muted_at === null;
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

    /**
     * Apply the inbox's smart-folder filter, feed selection, and text search.
     * Shared by the inbox listing and "mark all read" so both operate on the
     * exact same visible set.
     */
    public function scopeInboxFilter(Builder $query, string $filter, int $feedId, string $search, string $author = '', string $exclude = ''): Builder
    {
        return $query
            ->when($filter === 'starred', fn ($q) => $q->starred())
            ->when($filter === 'unread', fn ($q) => $q->unread())
            ->when($filter === 'today', fn ($q) => $q->where('published_at', '>=', now()->startOfDay()))
            ->when($filter === 'yesterday', fn ($q) => $q->whereBetween('published_at', [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()]))
            ->when($filter === 'week', fn ($q) => $q->where('published_at', '>=', now()->subDays(7)))
            ->when($filter === 'month', fn ($q) => $q->where('published_at', '>=', now()->subDays(30)))
            ->when($filter === 'recent', fn ($q) => $q->where('created_at', '>=', now()->subDays(7)))
            ->when($filter === 'read', fn ($q) => $q->where('is_read', true)->where('read_at', '>=', now()->subDays(7)))
            ->when($feedId > 0, fn ($q) => $q->forFeed($feedId))
            ->when($author !== '', fn ($q) => $q->where('author', 'like', "%{$author}%"))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('title', 'like', "%{$search}%")
                        ->orWhere('excerpt', 'like', "%{$search}%")
                        ->orWhere('author', 'like', "%{$search}%");
                });
            })
            ->when($exclude !== '', function ($q) use ($exclude) {
                $q->where(function ($w) use ($exclude) {
                    $w->where('title', 'not like', "%{$exclude}%")
                        ->where('excerpt', 'not like', "%{$exclude}%");
                });
            });
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
