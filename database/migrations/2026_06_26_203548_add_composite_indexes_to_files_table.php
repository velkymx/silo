<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table) {
            // Hot path: file-only list per owner (FileSearch, QuotaService, PhotoController).
            $table->index(['owner_id', 'is_dir'], 'files_owner_isdir_idx');
            // Hot path: photo grid — owner + MIME prefix filter.
            $table->index(['owner_id', 'mime'], 'files_owner_mime_idx');
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropIndex('files_owner_isdir_idx');
            $table->dropIndex('files_owner_mime_idx');
        });
    }
};
