<?php

namespace App\Services\Rss;

/**
 * Tiny DTO returned by FeedDiscovery::discover() — the resolved feed URL
 * plus the <link title="…"> attribute when present, so the Add-Feed UI
 * can pre-fill the title field in one round trip.
 */
class DiscoveredFeed
{
    public function __construct(
        public readonly string $url,
        public readonly ?string $title = null,
    ) {}
}
