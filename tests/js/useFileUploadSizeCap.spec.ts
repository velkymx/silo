import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';

class FakeXHR {
    static instances: FakeXHR[] = [];
    static last: FakeXHR | null = null;
    open = vi.fn();
    send = vi.fn();
    setRequestHeader = vi.fn();
    upload = { onprogress: null as ((e: ProgressEvent) => void) | null };
    onload: (() => void) | null = null;
    onerror: (() => void) | null = null;
    onabort: (() => void) | null = null;
    status = 0;
    responseText = '';
    signal: AbortSignal;
    constructor() {
        FakeXHR.instances.push(this);
        FakeXHR.last = this;
        this.signal = new AbortController().signal;
    }
}

beforeEach(() => {
    FakeXHR.instances = [];
    FakeXHR.last = null;
    (globalThis as unknown as { XMLHttpRequest: unknown }).XMLHttpRequest = FakeXHR;
});
afterEach(() => {
    delete (globalThis as unknown as { XMLHttpRequest?: unknown }).XMLHttpRequest;
});

const csrf = vi.hoisted(() => vi.fn(() => 'test-csrf'));
vi.mock('@/lib/http', () => ({ csrfToken: csrf }));

import { useFileUpload } from '@/composables/useFileUpload';

describe('useFileUpload per-file size cap (FE-P2-56)', () => {
    it('marks oversize files as error without starting an XHR', () => {
        const u = useFileUpload({ url: '/upload', parentId: null, maxSizeKb: 100, autoStart: true });
        const big = new File([new Uint8Array(200 * 1024)], 'big.bin'); // 200 KB > 100 KB
        const small = new File([new Uint8Array(10 * 1024)], 'small.bin');
        u.enqueue([big, small]);

        const items = u.items;
        expect(items).toHaveLength(2);
        const [itBig, itSmall] = items;
        expect(itBig.state).toBe('error');
        expect(itBig.error).toContain('100 KB');
        expect(itSmall.state).toBe('uploading');
        // Only the small file triggered an XHR.
        expect(FakeXHR.instances).toHaveLength(1);
    });
});
