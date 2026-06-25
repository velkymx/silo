<?php

namespace App\Models;

use App\Casts\VaultEncrypted;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A stored secret (password / API key) in the team vault. `secret` and `notes`
 * are encrypted at rest and hidden from serialization — they are only exposed
 * through the audited, rate-limited reveal endpoint.
 */
class VaultItem extends Model
{
    /** @use HasFactory<\Database\Factories\VaultItemFactory> */
    use HasFactory;

    protected $fillable = [
        'owner_id',
        'group_id',
        'name',
        'username',
        'url',
        'category',
        'secret',
        'notes',
        'last_rotated_at',
    ];

    /** Never let secrets leak into JSON / Inertia payloads by default. */
    protected $hidden = ['secret', 'notes'];

    protected function casts(): array
    {
        return [
            'secret' => VaultEncrypted::class,
            'notes' => VaultEncrypted::class,
            'last_rotated_at' => 'datetime',
        ];
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /** Items the user may see: their own plus any shared to their group. */
    public function scopeVisibleTo($query, User $user)
    {
        return $query->where(fn ($q) => $q
            ->where('owner_id', $user->id)
            ->when($user->group_id, fn ($w) => $w->orWhere('group_id', $user->group_id)));
    }
}
