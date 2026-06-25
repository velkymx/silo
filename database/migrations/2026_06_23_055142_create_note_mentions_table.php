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
        Schema::create('note_mentions', function (Blueprint $table) {
            $table->id();
            // The note containing the @mention.
            $table->foreignId('file_id')->constrained('files')->cascadeOnDelete();
            // The mentioned user.
            $table->foreignId('mentioned_user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['file_id', 'mentioned_user_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('note_mentions');
    }
};
