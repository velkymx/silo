// Pure detection of an in-progress `[[wikilink` or `@mention` token from the
// text preceding the caret on the current line. Kept framework-free so it can
// be unit-tested without a live Toast UI editor.

export type NoteTrigger = {
    type: 'wiki' | 'mention';
    query: string;
    // How many characters back from the caret the trigger token spans
    // (including the `[[` / `@` sigil) — used to select+replace on pick.
    matchLen: number;
};

const WIKI = /\[\[([^\]\n]*)$/;
// `@handle` not preceded by a word char, `@`, or `.` (so emails don't trigger).
const MENTION = /(?:^|[^\w@.])@([\w.-]*)$/;

/**
 * Inspect the line text up to the caret and report an active trigger, or null.
 */
export function detectTrigger(prefix: string): NoteTrigger | null {
    const wiki = prefix.match(WIKI);
    if (wiki) {
        return { type: 'wiki', query: wiki[1], matchLen: wiki[1].length + 2 };
    }

    const mention = prefix.match(MENTION);
    if (mention) {
        return { type: 'mention', query: mention[1], matchLen: mention[1].length + 1 };
    }

    return null;
}

/** The markdown text inserted when a suggestion is chosen. */
export function insertionFor(type: 'wiki' | 'mention', value: string): string {
    return type === 'wiki' ? `[[${value}]]` : `@${value}`;
}
