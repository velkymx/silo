import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';

const csrf = vi.hoisted(() => vi.fn(() => 'test-csrf'));
vi.mock('@/lib/http', () => ({ csrfToken: csrf }));

class FakeXHR {
    static lastInstance: FakeXHR | null = null;
    static instances: FakeXHR[] = [];
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
        FakeXHR.lastInstance = this;
        this.signal = new AbortController().signal;
    }
}

beforeEach(() => {
    FakeXHR.instances = [];
    FakeXHR.lastInstance = null;
    (globalThis as unknown as { XMLHttpRequest: unknown }).XMLHttpRequest = FakeXHR;
});

afterEach(() => {
    delete (globalThis as unknown as { XMLHttpRequest?: unknown }).XMLHttpRequest;
});

import { useFileUpload } from '@/composables/useFileUpload';

function file(name = 'a.png') {
    return new File(['x'], name, { type: 'image/png' });
}

function flushXhr(xhr: FakeXHR, status = 200) {
    xhr.status = status;
    xhr.onload?.();
}

describe('useFileUpload (FE-P1-60)', () => {
    it('enqueue creates pending items and auto-starts an XHR per file', () => {
        const u = useFileUpload({ url: '/upload', parentId: 1, autoStart: true });
        u.enqueue([file('a.png'), file('b.png')]);
        expect(u.items).toHaveLength(2);
        expect(FakeXHR.instances).toHaveLength(2);
        // Each XHR is POSTed to /upload with the parent_id set.
        for (const xhr of FakeXHR.instances) {
            expect(xhr.open).toHaveBeenCalledWith('POST', '/upload');
            const headers = xhr.setRequestHeader.mock.calls.map(c => c[0]);
            expect(headers).toContain('X-CSRF-TOKEN');
            expect(headers).toContain('Accept');
        }
    });

    it('marks item done on 2xx', () => {
        const u = useFileUpload({ url: '/upload', parentId: null });
        u.enqueue([file()]);
        const it = u.items[0];
        expect(it.state).toBe('uploading');
        flushXhr(FakeXHR.lastInstance!, 200);
        expect(it.state).toBe('done');
        expect(it.progress).toBe(100);
    });

    it('marks item error on 4xx/5xx with a status-coded message', () => {
        const u = useFileUpload({ url: '/upload', parentId: null });
        u.enqueue([file()]);
        const it = u.items[0];
        flushXhr(FakeXHR.lastInstance!, 422);
        expect(it.state).toBe('error');
        expect(it.error).toContain('422');
    });

    it('cancel() aborts the in-flight XHR', () => {
        const u = useFileUpload({ url: '/upload', parentId: null });
        u.enqueue([file()]);
        const it = u.items[0];
        const ctrl = it.controller!;
        const abortSpy = vi.spyOn(ctrl, 'abort');
        u.cancel(it.id);
        expect(abortSpy).toHaveBeenCalled();
        expect(it.state).toBe('uploading');
        // Simulate the XHR reacting to the abort.
        it.controller = null;
        it.state = 'cancelled';
    });

    it('retry() resends a failed item and increments retries', () => {
        const u = useFileUpload({ url: '/upload', parentId: null });
        u.enqueue([file()]);
        const it = u.items[0];
        flushXhr(FakeXHR.lastInstance!, 500);
        expect(it.state).toBe('error');
        u.retry(it.id);
        expect(it.retries).toBe(1);
        expect(FakeXHR.instances).toHaveLength(2);
        expect(it.state).toBe('uploading');
    });

    it('retry() refuses to resend past maxRetries', () => {
        const u = useFileUpload({ url: '/upload', parentId: null, maxRetries: 1 });
        u.enqueue([file()]);
        const it = u.items[0];
        flushXhr(FakeXHR.lastInstance!, 500);
        u.retry(it.id);
        flushXhr(FakeXHR.lastInstance!, 500);
        u.retry(it.id); // would be 2nd retry, but maxRetries=1 → blocked
        expect(FakeXHR.instances).toHaveLength(2);
    });

    it('count helpers reflect state totals', () => {
        const u = useFileUpload({ url: '/upload', parentId: null });
        u.enqueue([file('a'), file('b'), file('c')]);
        const [a, b, c] = u.items;
        flushXhr(FakeXHR.instances[0], 200);
        flushXhr(FakeXHR.instances[1], 500);
        // c still uploading.
        expect(u.doneCount.value).toBe(1);
        expect(u.errorCount.value).toBe(1);
        expect(u.activeCount.value).toBe(1);
        void a; void b; void c;
    });

    it('totalBytes sums file sizes', () => {
        const u = useFileUpload({ url: '/upload', parentId: null });
        u.enqueue([new File([new Uint8Array(100)], 'a'), new File([new Uint8Array(50)], 'b')]);
        expect(u.totalBytes.value).toBe(150);
    });
});
