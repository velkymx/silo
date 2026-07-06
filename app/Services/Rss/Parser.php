<?php

namespace App\Services\Rss;

use Carbon\Carbon;
use SimpleXMLElement;
use Throwable;

/**
 * Pure parser: bytes in, array of normalized entries out. No DB, no events,
 * no HTTP — easy to unit-test and reused by the migration command.
 *
 * Returned entries: ['guid' => string, 'title' => string, 'url' => string,
 * 'content' => string, 'excerpt' => string, 'author' => ?string,
 * 'published_at' => ?Carbon].
 */
class Parser
{
    /**
     * @return array{title: ?string, site_url: ?string, entries: array<int, array<string, mixed>>}
     */
    public function parse(string $xml): array
    {
        $xml = $this->stripBom($xml);

        try {
            $doc = new SimpleXMLElement($xml, LIBXML_NOCDATA | LIBXML_NOERROR | LIBXML_NOWARNING);
        } catch (Throwable) {
            return ['title' => null, 'site_url' => null, 'entries' => []];
        }

        $root = $doc->getName();

        return $root === 'rss' ? $this->parseRss($doc) : $this->parseAtom($doc);
    }

    /**
     * @return array{title: ?string, site_url: ?string, entries: array<int, array<string, mixed>>}
     */
    private function parseRss(SimpleXMLElement $doc): array
    {
        $channel = $doc->channel ?? null;
        $title = $channel ? trim((string) $channel->title) : null;
        $siteUrl = $channel ? trim((string) $channel->link) : null;
        $entries = [];

        if (! $channel) {
            return ['title' => $title, 'site_url' => $siteUrl, 'entries' => $entries];
        }

        foreach ($channel->item as $item) {
            $content = $this->content($item->children('content', true)->encoded ?? null, $item->description ?? null);
            $entries[] = [
                'guid' => $this->guid((string) ($item->guid ?? ''), (string) ($item->link ?? '')),
                'title' => trim((string) ($item->title ?? '')),
                'url' => trim((string) ($item->link ?? '')),
                'content' => $content,
                'excerpt' => $this->excerpt($item->description ?? null),
                'author' => $this->authorRss($item),
                'categories' => $this->categoriesRss($item),
                'image_url' => $this->imageRss($item, $content),
                'published_at' => $this->parseDate((string) ($item->pubDate ?? '')),
            ];
        }

        return ['title' => $title ?: null, 'site_url' => $siteUrl ?: null, 'entries' => $entries];
    }

    /**
     * @return array{title: ?string, site_url: ?string, entries: array<int, array<string, mixed>>}
     */
    private function parseAtom(SimpleXMLElement $doc): array
    {
        $ns = $doc->getNamespaces(true);
        $nsKey = $ns['atom'] ?? 'http://www.w3.org/2005/Atom';
        $atom = $doc->children($nsKey);
        $title = isset($atom->title) ? trim((string) $atom->title) : null;

        $siteUrl = null;
        if (isset($atom->link)) {
            foreach ($atom->link as $link) {
                $rel = (string) $link['rel'];
                $href = (string) $link['href'];
                if ($rel === '' || $rel === 'alternate') {
                    $siteUrl = $href ?: $siteUrl;
                    break;
                }
            }
        }

        $entries = [];
        foreach ($atom->entry as $entry) {
            $entryUrl = '';
            if (isset($entry->link)) {
                foreach ($entry->link as $link) {
                    $rel = (string) $link['rel'];
                    if ($rel === '' || $rel === 'alternate') {
                        $entryUrl = (string) $link['href'];
                        break;
                    }
                }
            }
            $content = $this->content($entry->content ?? null, $entry->summary ?? null);
            $entries[] = [
                'guid' => $this->guid((string) ($entry->id ?? ''), $entryUrl),
                'title' => trim((string) ($entry->title ?? '')),
                'url' => $entryUrl,
                'content' => $content,
                'excerpt' => $this->excerpt($entry->summary ?? null),
                'author' => isset($entry->author->name) ? trim((string) $entry->author->name) : null,
                'categories' => $this->categoriesAtom($entry),
                'image_url' => $this->imageAtom($entry, $content),
                'published_at' => $this->parseDate((string) ($entry->published ?? $entry->updated ?? '')),
            ];
        }

        return ['title' => $title ?: null, 'site_url' => $siteUrl ?: null, 'entries' => $entries];
    }

    private function guid(string $guid, string $url): string
    {
        $guid = trim($guid);

        return $guid !== '' ? $guid : ($url !== '' ? $url : bin2hex(random_bytes(8)));
    }

    private function content(?SimpleXMLElement $primary, ?SimpleXMLElement $fallback): string
    {
        if ($primary !== null && (string) $primary !== '') {
            return (string) $primary;
        }
        if ($fallback !== null && (string) $fallback !== '') {
            return (string) $fallback;
        }

        return '';
    }

    private function excerpt(?SimpleXMLElement $node): string
    {
        if ($node === null) {
            return '';
        }
        $text = trim(strip_tags((string) $node));

        return $text === '' ? '' : (mb_strlen($text) > 280 ? mb_substr($text, 0, 277).'…' : $text);
    }

    private function authorRss(SimpleXMLElement $item): ?string
    {
        $dc = $item->children('dc', true);
        $name = isset($dc->creator) ? trim((string) $dc->creator) : null;
        if ($name) {
            return $name;
        }
        $author = isset($item->author) ? trim((string) $item->author) : null;

        return $author !== '' ? $author : null;
    }

    /**
     * @return array<int, string>
     */
    private function categoriesRss(SimpleXMLElement $item): array
    {
        $out = [];
        $dc = $item->children('dc', true);
        if (isset($dc->subject)) {
            foreach ($dc->subject as $subject) {
                $term = trim((string) $subject);
                if ($term !== '' && ! in_array($term, $out, true)) {
                    $out[] = $term;
                }
            }
        }
        if (isset($item->category)) {
            foreach ($item->category as $cat) {
                $term = trim((string) $cat);
                if ($term === '') {
                    continue;
                }
                // RSS often uses "domain/name" — keep just the trailing slug.
                $slash = strrpos($term, '/');
                if ($slash !== false) {
                    $term = substr($term, $slash + 1);
                }
                if (! in_array($term, $out, true)) {
                    $out[] = $term;
                }
            }
        }
        $media = $item->children('media', true);
        if (isset($media->category)) {
            foreach ($media->category as $cat) {
                $term = trim((string) $cat);
                if ($term !== '' && ! in_array($term, $out, true)) {
                    $out[] = $term;
                }
            }
        }

        return $out;
    }

    /**
     * @return array<int, string>
     */
    private function categoriesAtom(SimpleXMLElement $entry): array
    {
        $out = [];
        if (isset($entry->category)) {
            foreach ($entry->category as $cat) {
                $term = $this->attr($cat, 'term') ?? trim((string) $cat);
                if ($term !== '' && ! in_array($term, $out, true)) {
                    $out[] = $term;
                }
            }
        }
        $media = $entry->children('media', true);
        if (isset($media->category)) {
            foreach ($media->category as $cat) {
                $term = $this->attr($cat, 'label') ?? $this->attr($cat, 'scheme') ?? trim((string) $cat);
                if ($term !== '' && ! in_array($term, $out, true)) {
                    $out[] = $term;
                }
            }
        }

        return $out;
    }

    private function imageRss(SimpleXMLElement $item, string $content): ?string
    {
        if (isset($item->enclosure)) {
            foreach ($item->enclosure as $enc) {
                $type = strtolower((string) ($this->attr($enc, 'type') ?? ''));
                $url = $this->attr($enc, 'url');
                if ($url !== null && str_starts_with($type, 'image/')) {
                    return $url;
                }
            }
        }
        $media = $item->children('media', true);
        if (isset($media->thumbnail)) {
            foreach ($media->thumbnail as $thumb) {
                $url = $this->attr($thumb, 'url');
                if ($url !== null) {
                    return $url;
                }
            }
        }
        if (isset($media->content)) {
            foreach ($media->content as $mc) {
                $type = strtolower((string) ($this->attr($mc, 'type') ?? ''));
                $url = $this->attr($mc, 'url');
                if ($url !== null && str_starts_with($type, 'image/')) {
                    return $url;
                }
            }
        }
        $itunes = $item->children('itunes', true);
        if (isset($itunes->image)) {
            $url = $this->attr($itunes->image, 'href');
            if ($url !== null) {
                return $url;
            }
        }

        return $this->firstImageInHtml($content);
    }

    private function imageAtom(SimpleXMLElement $entry, string $content): ?string
    {
        $media = $entry->children('media', true);
        if (isset($media->thumbnail)) {
            foreach ($media->thumbnail as $thumb) {
                $url = $this->attr($thumb, 'url');
                if ($url !== null) {
                    return $url;
                }
            }
        }
        if (isset($media->content)) {
            foreach ($media->content as $mc) {
                $type = strtolower((string) ($this->attr($mc, 'type') ?? ''));
                $url = $this->attr($mc, 'url');
                if ($url !== null && str_starts_with($type, 'image/')) {
                    return $url;
                }
            }
        }

        return $this->firstImageInHtml($content);
    }

    private function firstImageInHtml(string $html): ?string
    {
        if ($html === '' || ! preg_match('/<img\b[^>]*\bsrc\s*=\s*(?:"([^"]*)"|\'([^\']*)\')/i', $html, $m)) {
            return null;
        }
        $url = $m[1] !== '' ? $m[1] : ($m[2] ?? '');

        return $url !== '' ? $url : null;
    }

    private function parseDate(string $raw): ?Carbon
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }
        try {
            return Carbon::parse($raw);
        } catch (Throwable) {
            return null;
        }
    }

    private function stripBom(string $xml): string
    {
        return str_starts_with($xml, "\xEF\xBB\xBF") ? substr($xml, 3) : $xml;
    }

    /**
     * Read an attribute off a SimpleXMLElement. Array access on iterated
     * children-from-namespaced-parent elements (e.g. $media->thumbnail[0]['url'])
     * silently returns '' in PHP 8 — ->attributes()['url'] is the working form.
     */
    private function attr(SimpleXMLElement $node, string $name): ?string
    {
        $attrs = $node->attributes();
        $value = $attrs[$name] ?? null;
        if ($value === null) {
            return null;
        }
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
