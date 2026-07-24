<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * ME-20: Scout's database engine falls back to LIKE %term% which forces
     * a full scan for the files table. Add a MySQL FULLTEXT index on `name`
     * (the heaviest-hit search column) and tag the `toSearchableArray()`
     * method with #[SearchUsingFullText] so Scout emits MATCH AGAINST.
     *
     * `mime` and `metadata` keep the LIKE path — small cardinality, FTS
     * adds no real value.
     *
     * SQLite (tests) ignores FULLTEXT; the LIKE fallback in DatabaseEngine
     * continues to work there. PostgreSQL would use to_tsvector — handled
     * automatically by Scout's SearchUsingFullText attribute.
     */
    public function up(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE files ADD FULLTEXT INDEX files_name_fulltext_idx (name)');
        }
        // SQLite/PostgreSQL: no-op; Scout's DatabaseEngine falls back to LIKE
        // on SQLite, and uses to_tsvector on PostgreSQL when SearchUsingFullText
        // is set (which still requires a GIN index — out of scope here).
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('ALTER TABLE files DROP INDEX files_name_fulltext_idx');
        }
    }
};
