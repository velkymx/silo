<?php

namespace App\Services\Rss;

use App\Models\RssFeed;

/**
 * Pure builder: collection of feeds in, OPML 2.0 XML out. Mirrors
 * App\Services\Rss\OpmlParser so the round-trip is lossless — every
 * import via OpmlParser can be re-exported and parsed back to the
 * same (url, title, folder) tuples.
 *
 * Folders with a slash (the flattened path produced by the importer)
 * stay flat in the output. The OPML spec supports nested <outline>
 * trees, but the importer flattens them on parse, so re-exporting
 * nested structure would not round-trip.
 */
class OpmlExporter
{
    /**
     * @param  iterable<RssFeed>  $feeds
     */
    public function build(iterable $feeds, ?string $title = null): string
    {
        $groups = [];
        $noFolder = [];
        foreach ($feeds as $feed) {
            $folder = trim((string) ($feed->folder ?? ''));
            if ($folder === '') {
                $noFolder[] = $feed;
            } else {
                $groups[$folder][] = $feed;
            }
        }
        ksort($groups);

        $docTitle = $title !== null && $title !== '' ? $title : 'Subscriptions';

        $out = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $out .= "<opml version=\"2.0\">\n";
        $out .= '  <head><title>'.$this->escape($docTitle).'</title>';
        $out .= '<dateCreated>'.now()->toRfc822String().'</dateCreated>';
        $out .= "</head>\n";
        $out .= "  <body>\n";
        foreach ($noFolder as $feed) {
            $out .= $this->feedOutline($feed, 4);
        }
        foreach ($groups as $folder => $items) {
            $out .= '    <outline text="'.$this->escape($folder).'" title="'.$this->escape($folder).'">'.PHP_EOL;
            foreach ($items as $feed) {
                $out .= $this->feedOutline($feed, 6);
            }
            $out .= '    </outline>'.PHP_EOL;
        }
        $out .= "  </body>\n</opml>\n";

        return $out;
    }

    private function feedOutline(RssFeed $feed, int $indent): string
    {
        $space = str_repeat(' ', $indent);
        $title = $this->escape((string) $feed->title);
        $url = $this->escape((string) $feed->url);
        $siteUrl = $this->escape((string) ($feed->site_url ?? ''));

        return $space.'<outline type="rss" text="'.$title.'" title="'.$title.'" xmlUrl="'.$url.'" htmlUrl="'.$siteUrl.'"/>'.PHP_EOL;
    }

    private function escape(string $s): string
    {
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
