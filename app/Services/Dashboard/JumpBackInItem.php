<?php

namespace App\Services\Dashboard;

use Illuminate\Contracts\Support\Arrayable;

/**
 * The single "pick up where you left off" target on the home screen: the
 * user's most recently content-edited file or note. Immutable; the frontend
 * renders it as one prominent button.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class JumpBackInItem implements Arrayable
{
    public function __construct(
        public int $id,
        public string $title,
        public string $type,
        public string $url,
        public string $editedAt,
    ) {}

    /**
     * @return array{id: int, title: string, type: string, url: string, editedAt: string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'type' => $this->type,
            'url' => $this->url,
            'editedAt' => $this->editedAt,
        ];
    }
}
