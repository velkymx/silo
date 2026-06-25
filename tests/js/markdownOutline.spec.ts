import { describe, it, expect } from 'vitest';
import { extractHeadings } from '@/lib/markdownOutline';

describe('extractHeadings', () => {
    it('extracts headings with level and line', () => {
        const md = '# Title\n\nintro\n\n## Section\ntext\n### Sub';
        expect(extractHeadings(md)).toEqual([
            { level: 1, text: 'Title', line: 0 },
            { level: 2, text: 'Section', line: 4 },
            { level: 3, text: 'Sub', line: 6 },
        ]);
    });

    it('ignores headings inside fenced code', () => {
        const md = '# Real\n\n```\n# Fake\n```\n## Also Real';
        expect(extractHeadings(md).map((h) => h.text)).toEqual(['Real', 'Also Real']);
    });

    it('requires a space after the hashes', () => {
        expect(extractHeadings('#nope\n# yes')).toEqual([{ level: 1, text: 'yes', line: 1 }]);
    });

    it('ignores seven or more hashes', () => {
        expect(extractHeadings('####### too deep')).toEqual([]);
    });

    it('returns nothing for headingless text', () => {
        expect(extractHeadings('just words\nmore words')).toEqual([]);
    });
});
