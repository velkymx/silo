<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedSearch extends Model
{
    protected $fillable = ['owner_id', 'name', 'params'];

    protected $casts = ['params' => 'array'];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** True when the saved query is a global cross-content search. */
    public function isGlobal(): bool
    {
        return ! empty($this->params['q'] ?? null);
    }

    /** The route this saved search should resolve to. */
    public function routeName(): string
    {
        return $this->isGlobal() ? 'search.index' : 'files.index';
    }

    /** The query string this saved search should carry. */
    public function routeParams(): array
    {
        return $this->isGlobal() ? ['q' => $this->params['q']] : $this->params;
    }
}
