<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('rss_refresh_logs');
        Schema::create('rss_refresh_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rss_feed_id')->constrained('rss_feeds')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->index();
            $table->timestamp('completed_at')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('response_time_ms')->nullable();
            $table->unsignedTinyInteger('outcome');
            $table->unsignedInteger('new_items_count')->default(0);
            $table->string('error', 500)->nullable();
            $table->timestamps();

            $table->index(['rss_feed_id', 'started_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rss_refresh_logs');
    }
};
