<?php

namespace App\Http\Resources;

use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_dir' => false,
            'size' => $this->size,
            'mime' => $this->mime,
            'type' => strtolower(pathinfo($this->name, PATHINFO_EXTENSION)),
            'url' => route('files.raw', $this->resource),
            'status' => $this->status,
            'metadata' => $this->metadata,
            'thumb_url' => $this->thumbnail_path ? route('files.thumbnail', $this->resource) : null,
            'tags' => $this->relationLoaded('tags')
                ? $this->tags->map(fn (Tag $t) => ['id' => $t->id, 'name' => $t->name, 'color' => $t->color])->values()
                : [],
            'hash' => $this->hash,
            'version' => $this->version,
            'starred' => (bool) $this->starred,
            'versions' => $this->relationLoaded('versions')
                ? VersionResource::collection($this->versions)->resolve($request)
                : [],
            'created_at' => $this->created_at->format('Y-m-d H:i'),
        ];
    }
}
