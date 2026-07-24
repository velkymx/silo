<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rss_feeds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->text('url');
            $table->text('site_url')->nullable();
            $table->text('description')->nullable();
            $table->string('favicon_path')->nullable();
            $table->string('etag', 191)->nullable();
            $table->string('last_modified', 191)->nullable();
            $table->timestamp('last_fetched_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('refresh_interval_minutes')->default(60);
            $table->string('folder')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['user_id', 'enabled']);
            $table->index(['user_id', 'folder']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rss_feeds');
    }
};
