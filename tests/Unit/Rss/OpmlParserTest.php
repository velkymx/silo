<?php

namespace Tests\Unit\Rss;

use App\Services\Rss\OpmlParser;
use PHPUnit\Framework\TestCase;

class OpmlParserTest extends TestCase
{
    public function test_parses_flat_opml_with_multiple_feeds(): void
    {
        $opml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<opml version="2.0">
  <head><title>Subscriptions</title></head>
  <body>
    <outline type="rss" text="Laravel News" xmlUrl="https://laravel-news.com/feed.xml" htmlUrl="https://laravel-news.com"/>
    <outline type="rss" text="PHP Weekly" xmlUrl="https://phpweekly.example/feed" htmlUrl="https://phpweekly.example"/>
  </body>
</opml>
XML;

        $entries = (new OpmlParser)->parse($opml);

        $this->assertCount(2, $entries);
        $this->assertSame('https://laravel-news.com/feed.xml', $entries[0]['url']);
        $this->assertSame('Laravel News', $entries[0]['title']);
        $this->assertNull($entries[0]['folder']);
        $this->assertSame('PHP Weekly', $entries[1]['title']);
    }

    public function test_nested_folders_are_flattened_with_slash_path(): void
    {
        $opml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<opml version="2.0">
  <head><title>Subs</title></head>
  <body>
    <outline text="Tech" title="Tech">
      <outline type="rss" text="Hacker News" xmlUrl="https://hnrss.org/frontpage"/>
      <outline text="Web" title="Web">
        <outline type="rss" text="CSS-Tricks" xmlUrl="https://css-tricks.com/feed/"/>
      </outline>
    </outline>
  </body>
</opml>
XML;

        $entries = (new OpmlParser)->parse($opml);

        $this->assertCount(2, $entries);
        $this->assertSame('Tech', $entries[0]['folder']);
        $this->assertSame('Hacker News', $entries[0]['title']);
        $this->assertSame('Tech / Web', $entries[1]['folder']);
        $this->assertSame('CSS-Tricks', $entries[1]['title']);
    }

    public function test_uses_xmlurl_when_title_missing(): void
    {
        $opml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<opml version="2.0">
  <body>
    <outline type="rss" xmlUrl="https://example.com/feed"/>
  </body>
</opml>
XML;

        $entries = (new OpmlParser)->parse($opml);
        $this->assertCount(1, $entries);
        $this->assertSame('https://example.com/feed', $entries[0]['url']);
        $this->assertSame('https://example.com/feed', $entries[0]['title']);
    }

    public function test_skips_outlines_without_xmlurl(): void
    {
        $opml = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<opml version="2.0">
  <body>
    <outline text="Just a folder"/>
    <outline type="rss" text="With feed" xmlUrl="https://example.com/feed"/>
  </body>
</opml>
XML;

        $entries = (new OpmlParser)->parse($opml);
        $this->assertCount(1, $entries);
        $this->assertSame('With feed', $entries[0]['title']);
    }

    public function test_returns_empty_array_on_invalid_xml(): void
    {
        $this->assertSame([], (new OpmlParser)->parse('<not<valid'));
        $this->assertSame([], (new OpmlParser)->parse(''));
    }

    public function test_strips_utf8_bom(): void
    {
        $opml = "\xEF\xBB\xBF<?xml version=\"1.0\"?><opml version=\"2.0\"><body><outline type=\"rss\" text=\"A\" xmlUrl=\"https://a/feed\"/></body></opml>";
        $entries = (new OpmlParser)->parse($opml);
        $this->assertCount(1, $entries);
        $this->assertSame('A', $entries[0]['title']);
    }
}
