<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Permission extends Model
{
    /** @use HasFactory<\Database\Factories\PermissionFactory> */
    use HasFactory;

    public const SUBJECT_USER = 'user';

    public const SUBJECT_GROUP = 'group';

    public const ABILITY_READ = 'read';

    public const ABILITY_WRITE = 'write';

    public const ABILITY_DELETE = 'delete';

    public const ABILITY_SHARE = 'share';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'file_id',
        'subject_type',
        'subject_id',
        'ability',
    ];

    /**
     * The file this permission applies to.
     */
    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }
}
