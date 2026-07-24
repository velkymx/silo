<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One wall post. `wall_user_id` null = the shared dashboard wall; set = that
 * user's profile wall. Everything is public to every authenticated user;
 * `body` is sanitized HTML (stored post-sanitization, never raw).
 */
class WallPost extends Model
{
    /** @use HasFactory<\Database\Factories\WallPostFactory> */
    use HasFactory;

    protected $fillable = [
        'wall_user_id',
        'author_id',
        'body',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function wallOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'wall_user_id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(WallReaction::class);
    }

    /** Posts on one wall: a user's profile wall, or the dashboard wall (null). */
    public function scopeForWall(Builder $query, ?int $wallUserId): Builder
    {
        return $wallUserId === null
            ? $query->whereNull('wall_user_id')
            : $query->where('wall_user_id', $wallUserId);
    }
}
