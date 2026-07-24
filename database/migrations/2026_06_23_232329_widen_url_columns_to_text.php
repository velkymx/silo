<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Bookmarks and vault items store full URLs, which routinely exceed 255
     * chars (e.g. Chrome export tracking links). Widen to TEXT.
     */
    public function up(): void
    {
        Schema::table('bookmarks', function (Blueprint $table) {
            $table->text('url')->change();
        });
        Schema::table('vault_items', function (Blueprint $table) {
            $table->text('url')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookmarks', function (Blueprint $table) {
            $table->string('url')->change();
        });
        Schema::table('vault_items', function (Blueprint $table) {
            $table->string('url')->nullable()->change();
        });
    }
};
