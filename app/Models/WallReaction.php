<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One user's reaction (a VibeIcon key) on one wall post. Unique per
 * (post, user, icon): posting again with the same icon toggles it off.
 */
class WallReaction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'wall_post_id',
        'user_id',
        'icon',
        'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(WallPost::class, 'wall_post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
