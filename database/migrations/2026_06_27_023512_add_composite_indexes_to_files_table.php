<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->index(['owner_id', 'status']);
            $table->index(['owner_id', 'is_dir']);
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropIndex(['owner_id', 'status']);
            $table->dropIndex(['owner_id', 'is_dir']);
        });
    }
};
