<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    /**
     * The global unique (parent_id, name, owner_id) ignores soft deletes, so a
     * trashed name blocks re-uploading the same name (500). Live uniqueness is
     * enforced at the application layer (assertNoCollision + the upload overwrite
     * lookup, both SoftDeletes-aware); races are guarded by row locks.
     */
    public function up(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->dropUnique(['parent_id', 'name', 'owner_id']);
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->unique(['parent_id', 'name', 'owner_id']);
        });
    }
};
