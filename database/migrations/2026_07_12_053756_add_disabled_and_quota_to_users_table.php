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
        Schema::table('users', function (Blueprint $table) {
            // Set = the account is disabled: login refused, existing sessions
            // terminated on their next request.
            $table->timestamp('disabled_at')->nullable()->after('is_admin');
            // Per-user storage quota in MB. Null = the global
            // FILEMANAGER_USER_QUOTA_MB default; 0 = unlimited.
            $table->unsignedInteger('quota_mb')->nullable()->after('disabled_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['disabled_at', 'quota_mb']);
        });
    }
};
