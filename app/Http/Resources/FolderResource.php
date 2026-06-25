<?php

namespace App\Http\Resources;

use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FolderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_dir' => true,
            'starred' => (bool) $this->starred,
            'item_count' => $this->children_count ?? $this->children()->count(),
            'updated_at' => $this->updated_at->format('Y-m-d H:i'),
            'tags' => $this->relationLoaded('tags')
                ? $this->tags->map(fn (Tag $t) => ['id' => $t->id, 'name' => $t->name, 'color' => $t->color])->values()
                : [],
        ];
    }
}
