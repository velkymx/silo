// Pure extraction of ATX headings (`#` … `######`) from markdown, with the
// line number of each so the editor can jump to it. Headings inside fenced
// code blocks are ignored.

export type Heading = {
    level: number;
    text: string;
    line: number; // 0-based line index in the source
};

export function extractHeadings(markdown: string): Heading[] {
    const headings: Heading[] = [];
    let inFence = false;

    markdown.split('\n').forEach((line, index) => {
        const trimmed = line.trim();
        if (trimmed.startsWith('```') || trimmed.startsWith('~~~')) {
            inFence = !inFence;
            return;
        }
        if (inFence) return;

        const match = line.match(/^(#{1,6})\s+(.*\S)\s*$/);
        if (match) {
            headings.push({ level: match[1].length, text: match[2].trim(), line: index });
        }
    });

    return headings;
}
