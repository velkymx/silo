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
            // The parent_id foreign key uses this composite unique as its covering
            // index, so add a plain parent_id index before dropping it (MySQL
            // refuses to drop an index a foreign key still needs).
            $table->index('parent_id');
            $table->dropUnique(['parent_id', 'name', 'owner_id']);
        });
    }

    public function down(): void
    {
        Schema::table('files', function (Blueprint $table) {
            $table->unique(['parent_id', 'name', 'owner_id']);
            $table->dropIndex(['parent_id']);
        });
    }
};
