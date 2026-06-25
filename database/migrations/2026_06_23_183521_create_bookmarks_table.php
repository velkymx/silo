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
        Schema::create('bookmarks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('title');
            $table->string('url');
            $table->string('description')->nullable();
            // Bootstrap-icon name (e.g. "link-45deg") or a favicon URL.
            $table->string('icon')->nullable();
            $table->string('color')->nullable();
            // Free-text grouping shown as a section heading on the launchpad.
            $table->string('category')->nullable();
            // Visible to every authenticated user (a shared/company link).
            $table->boolean('shared')->default(false);
            $table->unsignedInteger('click_count')->default(0);
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index(['owner_id', 'category']);
            $table->index('shared');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookmarks');
    }
};
