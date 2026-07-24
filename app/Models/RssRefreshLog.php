<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only log of one refresh attempt for a single feed. Lets the
 * diagnostics surface answer questions the live row on rss_feeds
 * can't: refresh history, longest outage, items-per-day growth,
 * and a per-attempt timeline of HTTP statuses / response times.
 *
 * The outcome byte is kept tiny (TINYINT) and the enum lives on
 * the model so a UI can render the int as a label without a JOIN.
 */
class RssRefreshLog extends Model
{
    public const OUTCOME_SUCCESS = 0;

    public const OUTCOME_CONNECTION = 1;

    public const OUTCOME_HTTP_ERROR = 2;

    public const OUTCOME_PARSE_ERROR = 3;

    public const OUTCOME_SKIPPED = 4;

    protected $fillable = [
        'rss_feed_id',
        'user_id',
        'started_at',
        'completed_at',
        'http_status',
        'response_time_ms',
        'outcome',
        'new_items_count',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'http_status' => 'integer',
            'response_time_ms' => 'integer',
            'outcome' => 'integer',
            'new_items_count' => 'integer',
        ];
    }

    public function feed(): BelongsTo
    {
        return $this->belongsTo(RssFeed::class, 'rss_feed_id');
    }

    public function outcomeLabel(): string
    {
        return match ($this->outcome) {
            self::OUTCOME_SUCCESS => 'OK',
            self::OUTCOME_CONNECTION => 'Connection',
            self::OUTCOME_HTTP_ERROR => 'HTTP '.$this->http_status,
            self::OUTCOME_PARSE_ERROR => 'Parse',
            self::OUTCOME_SKIPPED => 'Skipped',
            default => 'Unknown',
        };
    }
}
