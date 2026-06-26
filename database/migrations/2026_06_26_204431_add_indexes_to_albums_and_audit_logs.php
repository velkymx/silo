<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ME-12: cover photo lookups on albums scan without an index.
        Schema::table('albums', function (Blueprint $table) {
            $table->index('cover_file_id', 'albums_cover_file_idx');
        });

        // ME-13: admin audit filter queries by (user_id, action) and (file_id, action).
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index(['user_id', 'action'], 'audit_logs_user_action_idx');
            $table->index(['file_id', 'action'], 'audit_logs_file_action_idx');
        });
    }

    public function down(): void
    {
        Schema::table('albums', function (Blueprint $table) {
            $table->dropIndex('albums_cover_file_idx');
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex('audit_logs_user_action_idx');
            $table->dropIndex('audit_logs_file_action_idx');
        });
    }
};
