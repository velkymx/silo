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
        Schema::table('backups', function (Blueprint $table) {
            // sha256 of the archive, computed at create time and verified before
            // a restore ever touches live data. Nullable so pre-existing backups
            // (created before integrity checks) still list, but they cannot be
            // integrity-verified.
            $table->string('checksum', 64)->nullable()->after('size');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('backups', function (Blueprint $table) {
            $table->dropColumn('checksum');
        });
    }
};
