<?php

use App\Models\RssItem;
use App\Services\Rss\HtmlSanitizer;
use Illuminate\Database\Migrations\Migration;

/**
 * Backfill: RSS item content was stored raw before HtmlSanitizer was wired
 * into RefreshFeed. GUID dedupe means those rows are never re-fetched, so
 * they would stay unsanitized forever. Re-clean every existing body once.
 *
 * Irreversible by design — the pre-sanitized (unsafe) HTML is intentionally
 * discarded and must not be restored.
 */
return new class extends Migration
{
    public function up(): void
    {
        $sanitizer = app(HtmlSanitizer::class);

        RssItem::query()
            ->whereNotNull('content')
            ->where('content', '!=', '')
            ->chunkById(200, function ($items) use ($sanitizer) {
                foreach ($items as $item) {
                    $clean = $sanitizer->clean($item->content);
                    if ($clean !== $item->content) {
                        $item->forceFill(['content' => $clean])->saveQuietly();
                    }
                }
            });
    }

    public function down(): void
    {
        // No-op: unsafe pre-sanitization HTML is intentionally not preserved.
    }
};
