<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Scout\Searchable;

/**
 * An internal link on the launchpad. Personal by default; `shared` makes it
 * visible to every authenticated user (a company-wide tool link).
 */
class Bookmark extends Model
{
    /** @use HasFactory<\Database\Factories\BookmarkFactory> */
    use HasFactory, Searchable;

    public const STATUS_PENDING = 'pending';

    public const STATUS_ALIVE = 'alive';

    public const STATUS_DEAD = 'dead';

    protected $fillable = [
        'owner_id',
        'title',
        'url',
        'description',
        'icon',
        'icon_path',
        'screenshot_path',
        'feed_url',
        'color',
        'category',
        'shared',
        'starred',
        'status',
        'last_checked_at',
        'click_count',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'shared' => 'boolean',
            'starred' => 'boolean',
            'click_count' => 'integer',
            'sort_order' => 'integer',
            'last_checked_at' => 'datetime',
        ];
    }

    /**
     * Columns Scout (database driver) matches a query against.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'title' => $this->title,
            'url' => $this->url,
            'description' => $this->description,
            'category' => $this->category,
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** Bookmarks the user may see: their own plus any shared by others. */
    public function scopeVisibleTo($query, int $userId)
    {
        return $query->where(fn ($q) => $q->where('owner_id', $userId)->orWhere('shared', true));
    }
}
