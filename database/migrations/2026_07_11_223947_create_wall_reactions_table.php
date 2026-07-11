<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('wall_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('wall_post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('icon', 32);
            $table->timestamp('created_at')->nullable();

            // Toggle semantics: one of each icon per user per post.
            $table->unique(['wall_post_id', 'user_id', 'icon']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wall_reactions');
    }
};
