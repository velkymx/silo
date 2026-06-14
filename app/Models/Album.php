<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Album extends Model
{
    protected $fillable = ['owner_id', 'name', 'description', 'cover_file_id'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function cover(): BelongsTo
    {
        return $this->belongsTo(File::class, 'cover_file_id');
    }

    public function photos(): BelongsToMany
    {
        return $this->belongsToMany(File::class, 'album_file', 'album_id', 'file_id')->withTimestamps();
    }
}
