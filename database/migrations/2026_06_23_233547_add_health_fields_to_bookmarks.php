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
        Schema::table('bookmarks', function (Blueprint $table) {
            // Liveness: pending until first check, then alive (2xx/3xx) or dead.
            $table->string('status')->default('pending')->after('shared');
            // Stored favicon + screenshot blobs (served via policy-gated routes).
            $table->string('icon_path')->nullable()->after('icon');
            $table->string('screenshot_path')->nullable()->after('icon_path');
            $table->timestamp('last_checked_at')->nullable()->after('screenshot_path');

            $table->index(['owner_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('bookmarks', function (Blueprint $table) {
            $table->dropColumn(['status', 'icon_path', 'screenshot_path', 'last_checked_at']);
        });
    }
};
