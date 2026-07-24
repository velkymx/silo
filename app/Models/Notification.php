<?php

namespace App\Models;

use Database\Factories\NotificationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A lightweight per-user notification row. Created by the automation engine
 * (in response to events) or directly by the app; consumed by the bell-icon
 * dropdown in the shell navbar.
 */
class Notification extends Model
{
    /** @use HasFactory<NotificationFactory> */
    use HasFactory;

    public const SEVERITY_LOW = 'low';

    public const SEVERITY_NORMAL = 'normal';

    public const SEVERITY_HIGH = 'high';

    protected $fillable = [
        'user_id',
        'type',
        'severity',
        'title',
        'body',
        'url',
        'data',
        'source_id',
        'source_type',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeUnread(Builder $query): Builder
    {
        return $query->whereNull('read_at');
    }

    public function scopeOwnedBy(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function markRead(): void
    {
        if ($this->read_at) {
            return;
        }
        $this->read_at = now();
        $this->save();
    }
}
