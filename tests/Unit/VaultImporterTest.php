<?php

namespace Tests\Unit;

use App\Services\VaultImporter;
use PHPUnit\Framework\TestCase;

class VaultImporterTest extends TestCase
{
    public function test_parses_chrome_password_csv(): void
    {
        $csv = "name,url,username,password,note\n"
            ."GitHub,https://github.com,octocat,s3cret,work\n"
            .",https://aws.amazon.com,root,awspw,\n";

        $rows = (new VaultImporter)->parse($csv);

        $this->assertCount(2, $rows);
        $this->assertSame(['name' => 'GitHub', 'url' => 'https://github.com', 'username' => 'octocat', 'secret' => 's3cret', 'notes' => 'work'], $rows[0]);
        // Missing name falls back to the URL host.
        $this->assertSame('aws.amazon.com', $rows[1]['name']);
        $this->assertNull($rows[1]['notes']);
    }

    public function test_skips_rows_without_a_password(): void
    {
        $csv = "name,url,username,password\nNoPass,https://x.com,bob,\nHasPass,https://y.com,sue,pw\n";

        $rows = (new VaultImporter)->parse($csv);

        $this->assertCount(1, $rows);
        $this->assertSame('HasPass', $rows[0]['name']);
    }
}
