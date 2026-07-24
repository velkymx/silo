<?php

namespace App\Services\Dashboard;

use Illuminate\Contracts\Support\Arrayable;

/**
 * One abnormality in the "Needs Attention" card (management by exception).
 * `tier` is red | yellow | blue and drives both ordering and subtle styling;
 * `url` links to where the issue is resolved.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class AttentionItem implements Arrayable
{
    public const TIER_RED = 'red';

    public const TIER_YELLOW = 'yellow';

    public const TIER_BLUE = 'blue';

    public function __construct(
        public string $tier,
        public string $title,
        public string $url,
    ) {}

    /**
     * @return array{tier: string, title: string, url: string}
     */
    public function toArray(): array
    {
        return [
            'tier' => $this->tier,
            'title' => $this->title,
            'url' => $this->url,
        ];
    }
}
