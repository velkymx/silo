<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'group_id',
        'is_admin',
        'avatar_path',
        'title',
        'department',
        'phone',
        'location',
        'bio',
        'blocked_keywords',
        'start_date',
        'manager_id',
        'disabled_at',
        'quota_mb',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'start_date' => 'date',
            'blocked_keywords' => 'array',
            'disabled_at' => 'datetime',
            'quota_mb' => 'integer',
        ];
    }

    /** Disabled accounts cannot log in; live sessions end on the next request. */
    public function isDisabled(): bool
    {
        return $this->disabled_at !== null;
    }

    // Relationships

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function files()
    {
        return $this->hasMany(File::class, 'owner_id');
    }

    /** The user this person reports to (org chart). */
    public function manager()
    {
        return $this->belongsTo(self::class, 'manager_id');
    }

    /** People who report to this user. */
    public function reports()
    {
        return $this->hasMany(self::class, 'manager_id');
    }
}
