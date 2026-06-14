import { describe, it, expect } from 'vitest';
import { sanitizeHtml } from '@/lib/sanitize';

describe('sanitizeHtml', () => {
    it('keeps benign table markup intact', () => {
        const out = sanitizeHtml('<table><tr><td>hi</td></tr></table>');
        expect(out).toContain('<td>hi</td>');
    });

    it('strips <script> tags', () => {
        const out = sanitizeHtml('<div>ok</div><script>alert(1)</script>');
        expect(out).toContain('ok');
        expect(out.toLowerCase()).not.toContain('<script');
    });

    it('strips inline event handlers (onerror/onclick)', () => {
        const out = sanitizeHtml('<img src="x" onerror="alert(1)"><a onclick="steal()">x</a>');
        expect(out.toLowerCase()).not.toContain('onerror');
        expect(out.toLowerCase()).not.toContain('onclick');
    });

    it('strips javascript: URLs', () => {
        const out = sanitizeHtml('<a href="javascript:alert(1)">x</a>');
        expect(out.toLowerCase()).not.toContain('javascript:');
    });
});
