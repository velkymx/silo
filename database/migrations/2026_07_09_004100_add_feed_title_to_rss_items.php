<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Denormalize the owning feed's title onto rss_items. It is part of the Scout
 * searchable set ("search feed names"), but the `database` Scout driver LIKEs
 * each searchable key as a real column — a derived relation value (feed?->title)
 * has no column and crashes the query. Store it, mirroring how user_id is
 * already denormalized from the feed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rss_items', function (Blueprint $table) {
            $table->string('feed_title')->nullable()->after('feed_id');
        });

        // Backfill from the owning feed.
        DB::table('rss_items')->orderBy('id')->chunkById(500, function ($rows) {
            $feedTitles = DB::table('rss_feeds')
                ->whereIn('id', collect($rows)->pluck('feed_id')->unique()->all())
                ->pluck('title', 'id');
            foreach ($rows as $row) {
                DB::table('rss_items')->where('id', $row->id)
                    ->update(['feed_title' => $feedTitles[$row->feed_id] ?? null]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('rss_items', function (Blueprint $table) {
            $table->dropColumn('feed_title');
        });
    }
};
