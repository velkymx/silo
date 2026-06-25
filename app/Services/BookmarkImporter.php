<?php

namespace App\Services;

use DOMElement;
use DOMNode;

/**
 * Parses a Chrome/Firefox/Safari "Netscape Bookmark File" HTML export into a
 * flat list of links. Categories are derived in document order: each <H3>
 * folder heading becomes the category for the links that follow it (the markup
 * is too loosely structured — unclosed <DT>/<p> — to rely on nesting).
 */
class BookmarkImporter
{
    /**
     * @return array<int, array{title: string, url: string, category: ?string}>
     */
    public function parse(string $html): array
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();

        $out = [];
        $folder = null;
        if ($dom->documentElement) {
            $this->scan($dom->documentElement, $folder, $out);
        }

        return $out;
    }

    /**
     * @param  array<int, array{title: string, url: string, category: ?string}>  $out
     */
    private function scan(DOMNode $node, ?string &$folder, array &$out): void
    {
        foreach ($node->childNodes as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }
            $tag = strtolower($child->nodeName);

            if ($tag === 'h3') {
                $folder = trim($child->textContent) ?: $folder;
            } elseif ($tag === 'a') {
                $url = $child->getAttribute('href');
                if ($this->isHttp($url)) {
                    $out[] = ['title' => trim($child->textContent) ?: $url, 'url' => $url, 'category' => $folder];
                }
            } else {
                $this->scan($child, $folder, $out);
            }
        }
    }

    private function isHttp(string $url): bool
    {
        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://');
    }
}
