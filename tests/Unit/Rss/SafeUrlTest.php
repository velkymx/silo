<?php

namespace Tests\Unit\Rss;

use App\Services\Rss\SafeUrl;
use App\Services\Rss\UnsafeUrlException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SafeUrlTest extends TestCase
{
    private SafeUrl $safe;

    protected function setUp(): void
    {
        parent::setUp();
        $this->safe = new SafeUrl;
    }

    /**
     * @return array<string, array{string}>
     */
    public static function unsafeUrls(): array
    {
        return [
            'loopback v4' => ['http://127.0.0.1/feed'],
            'loopback name-literal' => ['http://127.0.0.1:8080/x'],
            'private 10/8' => ['http://10.0.0.5/feed'],
            'private 172.16/12' => ['http://172.16.4.4/feed'],
            'private 192.168/16' => ['https://192.168.1.1/admin'],
            'link-local metadata' => ['http://169.254.169.254/latest/meta-data/'],
            'ipv6 loopback' => ['http://[::1]/feed'],
            'ipv6 ula' => ['http://[fc00::1]/feed'],
            'bad scheme file' => ['file:///etc/passwd'],
            'bad scheme gopher' => ['gopher://127.0.0.1/'],
            'bad scheme javascript' => ['javascript:alert(1)'],
            'no scheme' => ['example.com/feed'],
            'empty' => [''],
        ];
    }

    #[DataProvider('unsafeUrls')]
    public function test_rejects_unsafe_urls(string $url): void
    {
        $this->assertFalse($this->safe->isSafe($url), "{$url} should be unsafe");
    }

    public function test_allows_public_literal_ips(): void
    {
        $this->assertTrue($this->safe->isSafe('http://8.8.8.8/feed'));
        $this->assertTrue($this->safe->isSafe('https://1.1.1.1/'));
    }

    public function test_assert_throws_on_unsafe(): void
    {
        $this->expectException(UnsafeUrlException::class);
        $this->safe->assert('http://169.254.169.254/');
    }

    public function test_allow_redirects_config_guards_each_hop(): void
    {
        $config = $this->safe->allowRedirects(5);

        $this->assertSame(5, $config['max']);
        $this->assertIsCallable($config['on_redirect']);

        $this->expectException(UnsafeUrlException::class);
        ($config['on_redirect'])(null, null, 'http://10.0.0.1/internal');
    }
}
