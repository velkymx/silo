<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use App\Services\VirusScanner;

/**
 * CR-01: file_put_contents($tmp, $stream) wrote literal "Resource id #N"
 * instead of file bytes when $stream is a PHP resource. Verify that the
 * temp file contains the actual stream content after the fix.
 */
class VirusScannerStreamTest extends TestCase
{
    public function test_temp_file_contains_stream_bytes_not_resource_string(): void
    {
        $content = "EICAR-TEST-FILE content bytes\n";
        $stream = fopen('php://memory', 'rb+');
        fwrite($stream, $content);
        rewind($stream);

        $tmp = (new VirusScanner())->writeTempFromStream($stream);

        try {
            $this->assertSame($content, file_get_contents($tmp));
            $this->assertStringNotContainsString('Resource id', file_get_contents($tmp));
        } finally {
            if (is_file($tmp)) {
                unlink($tmp);
            }
        }
    }
}
