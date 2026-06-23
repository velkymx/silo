<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An internal link on the launchpad. Personal by default; `shared` makes it
 * visible to every authenticated user (a company-wide tool link).
 */
class Bookmark extends Model
{
    /** @use HasFactory<\Database\Factories\BookmarkFactory> */
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'title',
        'url',
        'description',
        'icon',
        'color',
        'category',
        'shared',
        'click_count',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'shared' => 'boolean',
            'click_count' => 'integer',
            'sort_order' => 'integer',
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
