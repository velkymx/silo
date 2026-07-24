<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rss_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('feed_id')->constrained('rss_feeds')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('guid');
            $table->string('title');
            $table->longText('content')->nullable();
            $table->text('excerpt')->nullable();
            $table->string('author')->nullable();
            $table->text('url');
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_read')->default(false);
            $table->boolean('is_starred')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->timestamp('starred_at')->nullable();
            $table->timestamps();

            $table->unique(['feed_id', 'guid']);
            $table->index(['user_id', 'is_read', 'published_at']);
            $table->index(['user_id', 'is_starred']);
            $table->index(['user_id', 'feed_id', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rss_items');
    }
};
