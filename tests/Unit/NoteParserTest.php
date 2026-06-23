<?php

namespace Tests\Unit;

use App\Services\NoteParser;
use PHPUnit\Framework\TestCase;

class NoteParserTest extends TestCase
{
    private NoteParser $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new NoteParser;
    }

    public function test_extracts_wikilink_titles(): void
    {
        $links = $this->parser->extractWikiLinks('See [[Meeting Notes]] and [[Roadmap]].');

        $this->assertSame(
            [['title' => 'Meeting Notes', 'alias' => null], ['title' => 'Roadmap', 'alias' => null]],
            $links,
        );
    }

    public function test_extracts_wikilink_alias(): void
    {
        $links = $this->parser->extractWikiLinks('Read [[Roadmap|the plan]] today.');

        $this->assertSame([['title' => 'Roadmap', 'alias' => 'the plan']], $links);
    }

    public function test_dedupes_wikilinks_and_trims_whitespace(): void
    {
        $links = $this->parser->extractWikiLinks('[[ Foo ]] then [[Foo]] again');

        $this->assertSame([['title' => 'Foo', 'alias' => null]], $links);
    }

    public function test_extracts_mentions_without_duplicates(): void
    {
        $this->assertSame(['alice', 'bob'], $this->parser->extractMentions('hi @alice and @bob and @alice'));
    }

    public function test_does_not_treat_email_as_mention(): void
    {
        $this->assertSame([], $this->parser->extractMentions('reach me at foo@example.com'));
    }

    public function test_extracts_tags(): void
    {
        $this->assertSame(['todo', 'project-x'], $this->parser->extractTags('#todo for #project-x #todo'));
    }

    public function test_extracts_nested_tags_whole(): void
    {
        $this->assertSame(['work/projects/x', 'home'], $this->parser->extractTags('#work/projects/x and #home'));
    }

    public function test_trims_trailing_slash_on_tags(): void
    {
        $this->assertSame(['work/projects'], $this->parser->extractTags('#work/projects/'));
    }

    public function test_ignores_tokens_inside_fenced_code(): void
    {
        $md = "intro [[Real]]\n\n```\nnot a [[Link]] or @mention or #tag\n```\n\nend";

        $this->assertSame([['title' => 'Real', 'alias' => null]], $this->parser->extractWikiLinks($md));
        $this->assertSame([], $this->parser->extractMentions($md));
        $this->assertSame([], $this->parser->extractTags($md));
    }

    public function test_ignores_tokens_inside_inline_code(): void
    {
        $md = 'use `@notARealMention` but mention @real';

        $this->assertSame(['real'], $this->parser->extractMentions($md));
    }
}
