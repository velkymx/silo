<?php

namespace App\Services;

/**
 * Pure, stateless extraction of note syntax — wikilinks, @mentions, and
 * #tags — from raw markdown. No database access; resolution to real records
 * is the NoteLinker's job. Tokens inside fenced or inline code are ignored so
 * documentation examples never create phantom links.
 */
class NoteParser
{
    /**
     * Extract `[[Title]]` / `[[Title|alias]]` wikilinks, in order, deduped.
     *
     * @return array<int, array{title: string, alias: string|null}>
     */
    public function extractWikiLinks(string $markdown): array
    {
        preg_match_all(
            '/\[\[([^\]\|]+?)(?:\|([^\]]+))?\]\]/',
            $this->stripCode($markdown),
            $matches,
            PREG_SET_ORDER,
        );

        $links = [];
        $seen = [];
        foreach ($matches as $m) {
            $title = trim($m[1]);
            $alias = isset($m[2]) && trim($m[2]) !== '' ? trim($m[2]) : null;
            if ($title === '') {
                continue;
            }
            $key = mb_strtolower($title).'|'.mb_strtolower((string) $alias);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $links[] = ['title' => $title, 'alias' => $alias];
        }

        return $links;
    }

    /**
     * Extract `@handle` mentions (lowercased, deduped). Emails are skipped — a
     * preceding word character or `@` disqualifies the match.
     *
     * @return array<int, string>
     */
    public function extractMentions(string $markdown): array
    {
        preg_match_all('/(?<![\w@.])@([A-Za-z0-9_.-]{1,50})/', $this->stripCode($markdown), $matches);

        return array_values(array_unique(array_map('mb_strtolower', array_map(
            fn (string $h) => rtrim($h, '.'),
            $matches[1],
        ))));
    }

    /**
     * Extract `#tag` inline tags, in order, deduped (case preserved). Nested
     * Bear-style tags (`#parent/child`) are returned whole; trailing slashes are
     * trimmed and empty segments collapsed so the sidebar can split cleanly.
     *
     * @return array<int, string>
     */
    public function extractTags(string $markdown): array
    {
        preg_match_all('/(?<!\S)#([A-Za-z0-9_\-\/]{1,50})/', $this->stripCode($markdown), $matches);

        $tags = array_map(
            fn (string $tag) => implode('/', array_filter(explode('/', $tag), fn ($p) => $p !== '')),
            $matches[1],
        );

        return array_values(array_unique(array_filter($tags, fn ($t) => $t !== '')));
    }

    /**
     * Blank out fenced (``` / ~~~) and inline (`…`) code spans so their
     * contents are never parsed as note syntax.
     */
    private function stripCode(string $markdown): string
    {
        $markdown = preg_replace('/```[\s\S]*?```/', ' ', $markdown) ?? $markdown;
        $markdown = preg_replace('/~~~[\s\S]*?~~~/', ' ', $markdown) ?? $markdown;

        return preg_replace('/`[^`]*`/', ' ', $markdown) ?? $markdown;
    }
}
