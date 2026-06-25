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
        Schema::create('note_links', function (Blueprint $table) {
            $table->id();
            // The note that contains the [[link]].
            $table->foreignId('source_file_id')->constrained('files')->cascadeOnDelete();
            // Resolved target note, or null while the title points at nothing yet.
            $table->foreignId('target_file_id')->nullable()->constrained('files')->nullOnDelete();
            // Raw [[Title]] text — kept so unresolved links can light up later
            // when a matching note is created/renamed.
            $table->string('target_title');
            // Display text (supports [[Title|alias]]; equals title when no alias).
            $table->string('link_text');
            // Denormalized owner so unresolved-title resolution stays owner-scoped
            // without joining through the source file.
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index('source_file_id');
            $table->index('target_file_id');
            $table->index(['owner_id', 'target_title']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('note_links');
    }
};
