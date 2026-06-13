<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Searchable;

class File extends Model
{
    /** @use HasFactory<\Database\Factories\FileFactory> */
    use HasFactory, Searchable, SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'path',
        'disk',
        'is_dir',
        'mime',
        'size',
        'hash',
        'status',
        'metadata',
        'thumbnail_path',
        'version',
        'starred',
        'parent_id',
        'owner_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_dir' => 'boolean',
            'size' => 'integer',
            'metadata' => 'array',
            'version' => 'integer',
            'starred' => 'boolean',
        ];
    }

    /**
     * The columns Scout (database driver) matches a query against.
     *
     * @return array<string, mixed>
     */
    public function toSearchableArray(): array
    {
        return [
            'name' => $this->name,
            'mime' => $this->mime,
            'metadata' => is_array($this->metadata) ? implode(' ', array_map('strval', $this->metadata)) : null,
        ];
    }

    /**
     * Only real files (never folders) are searchable.
     */
    public function shouldBeSearchable(): bool
    {
        return ! $this->is_dir;
    }

    /**
     * Owning user.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Parent folder.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * Direct children (files and folders).
     */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * Access-control entries for this file.
     */
    public function permissions(): HasMany
    {
        return $this->hasMany(Permission::class);
    }

    /**
     * Historical versions (prior blobs) of this file.
     */
    public function versions(): HasMany
    {
        return $this->hasMany(FileVersion::class)->orderByDesc('version');
    }

    /**
     * Tags applied to this file.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /**
     * Public share links pointing at this file.
     */
    public function shareLinks(): HasMany
    {
        return $this->hasMany(ShareLink::class);
    }

    /**
     * Scope to folders only.
     */
    public function scopeFolders($query)
    {
        return $query->where('is_dir', true);
    }

    /**
     * Scope to files only.
     */
    public function scopeFiles($query)
    {
        return $query->where('is_dir', false);
    }
}
