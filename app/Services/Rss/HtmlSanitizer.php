<?php

namespace App\Services\Rss;

use Symfony\Component\HtmlSanitizer\HtmlSanitizer as SymfonyHtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * Sanitizes untrusted article HTML pulled from remote RSS/Atom feeds before
 * it is stored and later rendered with v-html in the reader.
 *
 * Feed bodies are fully attacker-controlled (anyone can publish a feed the
 * user subscribes to), so the raw markup must never reach the DOM. The
 * config keeps benign article formatting — headings, lists, tables, links,
 * images — while dropping scripts, iframes, event handlers, inline styles,
 * and any non-http(s) URL scheme (javascript:, data:, …). Links are forced
 * to open safely; images are limited to http(s) sources.
 *
 * Symfony's HtmlSanitizer is allowlist-based: only the elements/attributes
 * explicitly allowed below survive, which is the safe default for this kind
 * of untrusted input.
 */
class HtmlSanitizer
{
    private SymfonyHtmlSanitizer $sanitizer;

    public function __construct()
    {
        $config = (new HtmlSanitizerConfig())
            // Structural + text formatting commonly used in article bodies.
            ->allowElement('p')
            ->allowElement('br')
            ->allowElement('hr')
            ->allowElement('span')
            ->allowElement('div')
            ->allowElement('h1')
            ->allowElement('h2')
            ->allowElement('h3')
            ->allowElement('h4')
            ->allowElement('h5')
            ->allowElement('h6')
            ->allowElement('strong')
            ->allowElement('b')
            ->allowElement('em')
            ->allowElement('i')
            ->allowElement('u')
            ->allowElement('s')
            ->allowElement('sub')
            ->allowElement('sup')
            ->allowElement('mark')
            ->allowElement('small')
            ->allowElement('blockquote')
            ->allowElement('cite')
            ->allowElement('q')
            ->allowElement('abbr')
            ->allowElement('code')
            ->allowElement('pre')
            ->allowElement('kbd')
            ->allowElement('samp')
            ->allowElement('ul')
            ->allowElement('ol')
            ->allowElement('li')
            ->allowElement('dl')
            ->allowElement('dt')
            ->allowElement('dd')
            ->allowElement('table')
            ->allowElement('thead')
            ->allowElement('tbody')
            ->allowElement('tfoot')
            ->allowElement('tr')
            ->allowElement('th')
            ->allowElement('td')
            ->allowElement('caption')
            ->allowElement('colgroup')
            ->allowElement('col')
            ->allowElement('figure')
            ->allowElement('figcaption')
            ->allowElement('a', ['href', 'title'])
            ->allowElement('img', ['src', 'alt', 'title', 'width', 'height'])
            // Keep only safe, well-known attributes.
            ->allowAttribute('colspan', ['th', 'td'])
            ->allowAttribute('rowspan', ['th', 'td'])
            // Restrict URLs to http(s); drops javascript:, data:, vbscript:, etc.
            ->allowLinkSchemes(['https', 'http', 'mailto'])
            ->allowMediaSchemes(['https', 'http'])
            // Never allow remote sub-resource loads beyond images we explicitly allow.
            ->forceHttpsUrls(false)
            // Links to external sites open in a new, isolated context.
            ->forceAttribute('a', 'rel', 'noopener noreferrer nofollow ugc')
            ->forceAttribute('a', 'target', '_blank')
            // Cap absurd payloads defensively (1 MiB of markup is plenty).
            ->withMaxInputLength(1_048_576);

        $this->sanitizer = new SymfonyHtmlSanitizer($config);
    }

    /**
     * Return a safe HTML fragment, or null when the input is empty/null.
     */
    public function clean(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }

        $clean = trim($this->sanitizer->sanitize($html));

        return $clean === '' ? null : $clean;
    }
}
