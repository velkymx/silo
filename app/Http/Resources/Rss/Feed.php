<?php

namespace App\Http\Resources\Rss;

use App\Models\RssFeed;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Wire shape for an RssFeed in the inbox sidebar. The shape excludes
 * bulky fields (description, etag, last_modified) that the sidebar
 * never renders — the model is loaded with ->get([...]) and the
 * resource is purely an output concern.
 */
class Feed extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var RssFeed $feed */
        $feed = $this->resource;

        return [
            'id' => $feed->id,
            'title' => $feed->title,
            'folder' => $feed->folder,
            'enabled' => $feed->enabled,
            'muted' => $feed->isMuted(),
            'muted_at' => optional($feed->muted_at)->toIso8601String(),
            'favicon_url' => $feed->favicon_path ? route('rss.feeds.favicon', $feed) : null,
            'refresh_interval_minutes' => (int) $feed->refresh_interval_minutes,
            'last_fetched_at' => optional($feed->last_fetched_at)->toIso8601String(),
            'last_success_at' => optional($feed->last_success_at)->toIso8601String(),
            'last_error' => $feed->last_error,
            'last_http_status' => $feed->last_http_status,
            'last_response_time_ms' => $feed->last_response_time_ms,
            'consecutive_failures' => $feed->consecutive_failures,
            'etag' => $feed->etag,
            'last_modified' => $feed->last_modified,
        ];
    }
}
