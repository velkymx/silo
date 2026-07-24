<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saved_searches', function (Blueprint $table) {
            $table->timestamp('last_run_at')->nullable()->after('is_favorite');
            $table->unsignedInteger('last_result_count')->default(0)->after('last_run_at');
        });
    }

    public function down(): void
    {
        Schema::table('saved_searches', function (Blueprint $table) {
            $table->dropColumn(['last_run_at', 'last_result_count']);
        });
    }
};
