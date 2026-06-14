import { describe, it, expect, vi, afterEach } from 'vitest';
import { triggerDownload } from '@/lib/download';

describe('triggerDownload', () => {
    afterEach(() => vi.restoreAllMocks());

    it('clicks a transient anchor instead of navigating', () => {
        const click = vi.spyOn(HTMLAnchorElement.prototype, 'click').mockImplementation(() => {});
        const create = vi.spyOn(document, 'createElement');

        triggerDownload('/download/7');

        const anchor = create.mock.results.find((r) => (r.value as HTMLElement).tagName === 'A')!.value as HTMLAnchorElement;
        expect(anchor.getAttribute('href')).toBe('/download/7');
        expect(anchor.download).toBe('');
        expect(click).toHaveBeenCalledOnce();
        // Anchor is removed again — it must not linger in the DOM.
        expect(document.querySelector('a[href="/download/7"]')).toBeNull();
    });
});
