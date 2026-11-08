<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Guards the MySQL-only test database. Asserting from inside PHPUnit proves
 * the test process itself uses the dedicated database (not just the shell
 * environment), and the query proves the connection is live. Keeping this
 * pinned also stops `migrate:fresh` from ever reaching a developer's real DB.
 */
class CiDatabaseConnectionTest extends TestCase
{
    public function test_phpunit_runs_on_the_dedicated_mysql_database(): void
    {
        $this->assertSame('mysql', DB::connection()->getDriverName());
        $this->assertSame('filemanager_test', DB::connection()->getDatabaseName());

        DB::select('SELECT 1');
    }
}
