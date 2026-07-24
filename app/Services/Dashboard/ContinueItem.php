<?php

namespace App\Services\Dashboard;

use Illuminate\Contracts\Support\Arrayable;

/**
 * One row in the "Continue Working" card: a recently-touched, likely-unfinished
 * object mixed across modules. Object-first — the frontend leads with `title`
 * and renders a secondary line from `reason` + a relative time off `at`.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class ContinueItem implements Arrayable
{
    public function __construct(
        public string $type,
        public string $title,
        public string $url,
        public string $at,
        public string $reason,
    ) {}

    /**
     * @return array{type: string, title: string, url: string, at: string, reason: string}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'url' => $this->url,
            'at' => $this->at,
            'reason' => $this->reason,
        ];
    }
}
