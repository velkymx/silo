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

    it('keeps the signed, same-origin RSS image proxy URL in img src', () => {
        // Inline article images are rewritten server-side to /rss/image?...;
        // the defense-in-depth pass must not strip the signature query param.
        const src = 'http://localhost/rss/image?url=https%3A%2F%2Fex.com%2Fa.png&amp;signature=abc123';
        const out = sanitizeHtml(`<p>Hi</p><img src="${src}" alt="x">`);
        expect(out).toContain('/rss/image');
        expect(out).toContain('signature=abc123');
    });
});
