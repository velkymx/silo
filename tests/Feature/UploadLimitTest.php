<?php

namespace Tests\Feature;

use App\Support\Uploads;
use Tests\TestCase;

class UploadLimitTest extends TestCase
{
    public function test_uses_configured_value_when_set(): void
    {
        config(['filemanager.max_upload_kb' => 51200]);

        $this->assertSame(51200, Uploads::maxKb());
    }

    public function test_falls_back_to_php_limit_when_unset(): void
    {
        config(['filemanager.max_upload_kb' => null]);

        // PHP's effective limit is the smaller of upload_max_filesize / post_max_size.
        $expectedKb = min(
            $this->iniKb('upload_max_filesize'),
            $this->iniKb('post_max_size')
        );

        $this->assertSame($expectedKb, Uploads::maxKb());
        $this->assertGreaterThan(0, Uploads::maxKb());
    }

    private function iniKb(string $key): int
    {
        $v = trim((string) ini_get($key));
        $n = (int) $v;
        switch (strtolower($v[strlen($v) - 1])) {
            case 'g': $n *= 1024; // no break
            case 'm': $n *= 1024; // no break
            case 'k': $n *= 1024;
        }

        return intdiv($n, 1024);
    }
}
