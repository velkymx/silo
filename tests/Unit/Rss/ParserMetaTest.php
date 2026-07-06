<?php

namespace Tests\Unit\Rss;

use App\Services\Rss\Parser;
use PHPUnit\Framework\TestCase;

class ParserMetaTest extends TestCase
{
    public function test_rss_extracts_categories_from_dc_and_item(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:dc="http://purl.org/dc/elements/1.1/">
  <channel>
    <item>
      <title>Post</title>
      <link>https://example.com/p</link>
      <guid>p1</guid>
      <category>Tech/Programming</category>
      <category>news</category>
      <dc:subject>Apple</dc:subject>
      <dc:subject>Apple</dc:subject>
    </item>
  </channel>
</rss>
XML;

        $r = (new Parser)->parse($xml);
        $this->assertSame(['Apple', 'Programming', 'news'], $r['entries'][0]['categories']);
    }

    public function test_rss_extracts_image_from_enclosure(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <item>
      <title>P</title>
      <link>https://example.com/p</link>
      <guid>p1</guid>
      <enclosure type="image/jpeg" url="https://example.com/hero.jpg"/>
      <enclosure type="audio/mpeg" url="https://example.com/song.mp3"/>
    </item>
  </channel>
</rss>
XML;

        $r = (new Parser)->parse($xml);
        $this->assertSame('https://example.com/hero.jpg', $r['entries'][0]['image_url']);
    }

    public function test_rss_extracts_image_from_media_thumbnail(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:media="http://search.yahoo.com/mrss/">
  <channel>
    <item>
      <title>P</title>
      <link>https://example.com/p</link>
      <guid>p1</guid>
      <media:thumbnail url="https://example.com/thumb.jpg"/>
    </item>
  </channel>
</rss>
XML;

        $r = (new Parser)->parse($xml);
        $this->assertSame('https://example.com/thumb.jpg', $r['entries'][0]['image_url']);
    }

    public function test_rss_falls_back_to_first_img_in_content(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <item>
      <title>P</title>
      <link>https://example.com/p</link>
      <guid>p1</guid>
      <description><![CDATA[<p>hi</p><img src="https://example.com/inline.png" alt=""/>]]></description>
    </item>
  </channel>
</rss>
XML;

        $r = (new Parser)->parse($xml);
        $this->assertSame('https://example.com/inline.png', $r['entries'][0]['image_url']);
    }

    public function test_atom_extracts_categories_from_term_attribute(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom">
  <entry>
    <id>tag:example,1</id>
    <title>P</title>
    <link href="https://example.com/p"/>
    <category term="php"/>
    <category term="laravel"/>
  </entry>
</feed>
XML;

        $r = (new Parser)->parse($xml);
        $this->assertSame(['php', 'laravel'], $r['entries'][0]['categories']);
    }

    public function test_atom_extracts_image_from_media_thumbnail(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<feed xmlns="http://www.w3.org/2005/Atom" xmlns:media="http://search.yahoo.com/mrss/">
  <entry>
    <id>tag:example,1</id>
    <title>P</title>
    <link href="https://example.com/p"/>
    <media:thumbnail url="https://example.com/a.jpg"/>
  </entry>
</feed>
XML;

        $r = (new Parser)->parse($xml);
        $this->assertSame('https://example.com/a.jpg', $r['entries'][0]['image_url']);
    }

    public function test_returns_empty_categories_and_null_image_when_absent(): void
    {
        $xml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <item><title>P</title><link>https://e/p</link><guid>p1</guid></item>
  </channel>
</rss>
XML;

        $r = (new Parser)->parse($xml);
        $this->assertSame([], $r['entries'][0]['categories']);
        $this->assertNull($r['entries'][0]['image_url']);
    }
}
