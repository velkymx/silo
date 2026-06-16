<?php

namespace Tests\Feature;

use Tests\TestCase;

class StorageDiskSecurityTest extends TestCase
{
    // C1: uploaded blobs must never default to a web-served public disk, or
    // they are reachable at /storage/... bypassing the policy gate + audit.
    public function test_uploads_default_to_a_private_disk(): void
    {
        // Read the shipped fallback with the env var stripped.
        $prev = getenv('FILEMANAGER_DISK');
        putenv('FILEMANAGER_DISK');
        unset($_ENV['FILEMANAGER_DISK'], $_SERVER['FILEMANAGER_DISK']);
        try {
            $config = require base_path('config/filemanager.php');
        } finally {
            if ($prev !== false) {
                putenv("FILEMANAGER_DISK={$prev}");
            }
        }

        $this->assertSame('local', $config['disk'], 'Uploads must default to the private "local" disk.');
        $this->assertArrayNotHasKey(
            'url',
            config("filesystems.disks.{$config['disk']}", []),
            'The upload disk must not expose a public web URL.',
        );
    }
}
