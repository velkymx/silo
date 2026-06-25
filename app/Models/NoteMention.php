<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An `@user` mention parsed out of a note's body.
 */
class NoteMention extends Model
{
    /** @use HasFactory<\Database\Factories\NoteMentionFactory> */
    use HasFactory;

    protected $fillable = [
        'file_id',
        'mentioned_user_id',
    ];

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class, 'file_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentioned_user_id');
    }
}
