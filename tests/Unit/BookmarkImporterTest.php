<?php

namespace Tests\Unit;

use App\Services\BookmarkImporter;
use PHPUnit\Framework\TestCase;

class BookmarkImporterTest extends TestCase
{
    private function importer(): BookmarkImporter
    {
        return new BookmarkImporter;
    }

    public function test_parses_links_with_folder_categories(): void
    {
        $html = <<<'HTML'
        <!DOCTYPE NETSCAPE-Bookmark-file-1>
        <DL><p>
            <DT><A HREF="https://example.com">Example</A>
            <DT><H3>Work</H3>
            <DL><p>
                <DT><A HREF="https://wiki.example.com">Wiki</A>
            </DL><p>
        </DL>
        HTML;

        $links = $this->importer()->parse($html);

        $this->assertSame([
            ['title' => 'Example', 'url' => 'https://example.com', 'category' => null],
            ['title' => 'Wiki', 'url' => 'https://wiki.example.com', 'category' => 'Work'],
        ], $links);
    }

    public function test_skips_non_http_entries(): void
    {
        $html = '<DL><DT><A HREF="javascript:void(0)">Bad</A><DT><A HREF="https://ok.com">Good</A></DL>';

        $links = $this->importer()->parse($html);

        $this->assertCount(1, $links);
        $this->assertSame('https://ok.com', $links[0]['url']);
    }
}
