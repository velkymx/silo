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
        Schema::table('files', function (Blueprint $table) {
            // When the file's CONTENT was last edited in-app (new version saved),
            // as distinct from `created_at` (uploaded) and `updated_at` (any row
            // change). Powers the "uploaded vs edited" date target in search.
            $table->timestamp('content_edited_at')->nullable()->after('updated_at')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropColumn('content_edited_at');
        });
    }
};
