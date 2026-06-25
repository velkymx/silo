<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A `[[wikilink]]` from one note (source) to another (target). The target may
 * be null while unresolved — the raw title is kept so the link resolves the
 * moment a matching note appears.
 */
class NoteLink extends Model
{
    /** @use HasFactory<\Database\Factories\NoteLinkFactory> */
    use HasFactory;

    protected $fillable = [
        'source_file_id',
        'target_file_id',
        'target_title',
        'link_text',
        'owner_id',
    ];

    public function source(): BelongsTo
    {
        return $this->belongsTo(File::class, 'source_file_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(File::class, 'target_file_id');
    }
}
