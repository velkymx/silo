<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Optional manual ordering (e.g. drag-to-reorder in the photo filmstrip).
     * Null = fall back to the natural date order.
     */
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->nullable()->after('starred')->index();
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
