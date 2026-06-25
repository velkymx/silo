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
        Schema::create('vault_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            // Optional team share: members of this group get read access.
            $table->foreignId('group_id')->nullable()->constrained('groups')->nullOnDelete();
            $table->string('name');
            $table->string('username')->nullable();
            $table->string('url')->nullable();
            $table->string('category')->nullable();
            // Ciphertext (AES-256-GCM via VaultCrypto) — never plaintext at rest.
            $table->text('secret');
            $table->text('notes')->nullable();
            $table->timestamp('last_rotated_at')->nullable();
            $table->timestamps();

            $table->index(['owner_id', 'category']);
            $table->index('group_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vault_items');
    }
};
