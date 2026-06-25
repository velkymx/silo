<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VersionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'version' => $this->version,
            'note' => $this->note,
            'size' => $this->size,
            'created_at' => $this->created_at->format('Y-m-d H:i'),
        ];
    }
}
