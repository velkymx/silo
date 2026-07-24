<?php

namespace App\Services\Rss;

use SimpleXMLElement;
use Throwable;

/**
 * Pure OPML 2.0 parser: bytes in, normalized feed list out. Mirrors
 * App\Services\Rss\Parser — no DB, no events, no HTTP, fully unit-testable.
 *
 * Returns one entry per <outline type="rss"> (or any outline that has an
 * xmlUrl attribute, which is the only field that actually defines a feed).
 * Nested <outline> trees are flattened; the folder/category is preserved
 * as the slash-joined ancestor titles so a one-level import keeps the
 * user's organization intact.
 *
 * Returned entries: ['url' => string, 'title' => string, 'folder' => ?string].
 */
class OpmlParser
{
    /**
     * @return array<int, array{url: string, title: string, folder: ?string}>
     */
    public function parse(string $xml): array
    {
        $xml = $this->stripBom($xml);

        try {
            $doc = new SimpleXMLElement($xml, LIBXML_NOCDATA | LIBXML_NOERROR | LIBXML_NOWARNING);
        } catch (Throwable) {
            return [];
        }

        $body = $doc->body ?? null;
        if (! $body) {
            return [];
        }

        $out = [];
        $this->walk($body->outline ?? [], [], $out);

        return $out;
    }

    /**
     * @param  iterable<SimpleXMLElement>  $nodes
     * @param  array<int, string>  $path
     * @param  array<int, array{url: string, title: string, folder: ?string}>  $out
     */
    private function walk(iterable $nodes, array $path, array &$out): void
    {
        foreach ($nodes as $node) {
            $title = trim((string) ($node['title'] ?? $node['text'] ?? ''));
            $url = trim((string) ($node['xmlUrl'] ?? ''));
            $type = strtolower(trim((string) ($node['type'] ?? '')));

            if ($url !== '' && ($type === '' || $type === 'rss' || $type === 'atom')) {
                $folder = $path === [] ? null : implode(' / ', array_filter($path, fn ($p) => $p !== ''));
                $out[] = [
                    'url' => $url,
                    'title' => $title !== '' ? $title : $url,
                    'folder' => $folder,
                ];

                continue;
            }

            // Folder node (no xmlUrl) — descend with this title on the path.
            if (isset($node->outline)) {
                $childPath = $path;
                if ($title !== '') {
                    $childPath[] = $title;
                }
                $this->walk($node->outline, $childPath, $out);
            }
        }
    }

    private function stripBom(string $xml): string
    {
        return str_starts_with($xml, "\xEF\xBB\xBF") ? substr($xml, 3) : $xml;
    }
}
