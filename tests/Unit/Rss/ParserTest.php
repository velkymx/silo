<?php

namespace Tests\Unit\Rss;

use App\Services\Rss\Parser;
use PHPUnit\Framework\TestCase;

class ParserTest extends TestCase
{
    public function test_parses_rss_2_with_basic_fields(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0"><channel>
  <title>Laravel News</title>
  <link>https://laravel-news.com</link>
  <description>News</description>
  <item>
    <title>Security patch</title>
    <link>https://laravel-news.com/security</link>
    <guid>https://laravel-news.com/security</guid>
    <pubDate>Mon, 06 Jul 2026 12:00:00 GMT</pubDate>
    <description><![CDATA[<p>Big deal</p>]]></description>
    <dc:creator xmlns:dc="http://purl.org/dc/elements/1.1/">Eric Barnes</dc:creator>
  </item>
</channel></rss>
XML;

        $parser = new Parser;
        $out = $parser->parse($xml);

        $this->assertSame('Laravel News', $out['title']);
        $this->assertSame('https://laravel-news.com', $out['site_url']);
        $this->assertCount(1, $out['entries']);
        $entry = $out['entries'][0];
        $this->assertSame('Security patch', $entry['title']);
        $this->assertSame('https://laravel-news.com/security', $entry['guid']);
        $this->assertSame('Eric Barnes', $entry['author']);
        $this->assertNotNull($entry['published_at']);
    }

    public function test_parses_atom_with_content(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <title>Atom Feed</title>
  <link rel="alternate" href="https://example.com"/>
  <entry>
    <id>tag:example,2026:1</id>
    <title>Hello</title>
    <link rel="alternate" href="https://example.com/hello"/>
    <published>2026-07-06T00:00:00Z</published>
    <author><name>Jane</name></author>
    <summary>World</summary>
  </entry>
</feed>
XML;

        $parser = new Parser;
        $out = $parser->parse($xml);

        $this->assertSame('Atom Feed', $out['title']);
        $this->assertCount(1, $out['entries']);
        $this->assertSame('tag:example,2026:1', $out['entries'][0]['guid']);
        $this->assertSame('Jane', $out['entries'][0]['author']);
        $this->assertSame('World', $out['entries'][0]['excerpt']);
    }

    public function test_garbage_returns_empty(): void
    {
        $parser = new Parser;
        $out = $parser->parse('not-xml-at-all');

        $this->assertSame([], $out['entries']);
    }

    public function test_falls_back_to_url_for_missing_guid(): void
    {
        $xml = <<<'XML'
<rss><channel>
  <item><title>No guid</title><link>https://x/y</link></item>
</channel></rss>
XML;

        $parser = new Parser;
        $out = $parser->parse($xml);

        $this->assertSame('https://x/y', $out['entries'][0]['guid']);
    }

    public function test_guidless_and_linkless_entry_gets_deterministic_surrogate(): void
    {
        $xml = <<<'XML'
<rss><channel>
  <item><title>Orphan</title><description>Same body</description><pubDate>Mon, 06 Jul 2026 12:00:00 GMT</pubDate></item>
</channel></rss>
XML;

        $parser = new Parser;
        $first = $parser->parse($xml)['entries'][0]['guid'];
        $second = $parser->parse($xml)['entries'][0]['guid'];

        $this->assertStringStartsWith('sha1:', $first);
        $this->assertSame($first, $second, 'Surrogate GUID must be stable across parses so dedupe works');
    }
}
