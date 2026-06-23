// Rewrites note syntax in raw markdown into renderable markdown, leaving code
// spans untouched:
//   [[Title]]        -> [Title](/notes?create=Title)
//   [[Title|alias]]  -> [alias](/notes?create=Title)
//   @handle          -> **@handle**   (visually distinct; resolution is server-side)
// Clicking a wikilink lands on /notes?create=Title, which opens the existing
// note or creates it (store is idempotent).

const WIKI = /\[\[([^\]\|\n]+?)(?:\|([^\]\n]+))?\]\]/g;
const MENTION = /(^|[^\w@.])@([\w.-]+)/g;

function transform(segment: string): string {
    return segment
        .replace(WIKI, (_m, title: string, alias?: string) => {
            const t = title.trim();
            const label = (alias ?? title).trim();
            return `[${label}](/notes?create=${encodeURIComponent(t)})`;
        })
        .replace(MENTION, (_m, pre: string, handle: string) => `${pre}**@${handle}**`);
}

export function linkifyNotes(markdown: string): string {
    // Split on fenced (``` … ```) and inline (` … `) code; odd segments are code.
    return markdown
        .split(/(```[\s\S]*?```|`[^`]*`)/g)
        .map((seg, i) => (i % 2 === 1 ? seg : transform(seg)))
        .join('');
}
