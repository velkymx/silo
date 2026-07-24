<?php

use App\Models\RssFeed;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * rss_feeds.url is TEXT, so it can't carry a unique index directly. Add a
 * sha1 url_hash column and enforce uniqueness on (user_id, url_hash) so a
 * user can't subscribe to the same feed twice (races included).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rss_feeds', function (Blueprint $table) {
            $table->char('url_hash', 40)->nullable()->after('url');
        });

        RssFeed::query()->select(['id', 'url'])->chunkById(200, function ($feeds) {
            foreach ($feeds as $feed) {
                $feed->forceFill(['url_hash' => sha1((string) $feed->url)])->saveQuietly();
            }
        });

        Schema::table('rss_feeds', function (Blueprint $table) {
            $table->unique(['user_id', 'url_hash']);
        });
    }

    public function down(): void
    {
        Schema::table('rss_feeds', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'url_hash']);
            $table->dropColumn('url_hash');
        });
    }
};
