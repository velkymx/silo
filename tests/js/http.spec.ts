import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { http, HttpError } from '@/lib/http';

describe('http (fetch wrapper)', () => {
    beforeEach(() => {
        document.head.innerHTML = '<meta name="csrf-token" content="tok123">';
    });
    afterEach(() => vi.restoreAllMocks());

    it('GET parses JSON and sends CSRF + same-origin', async () => {
        const fetchMock = vi.fn().mockResolvedValue({ ok: true, status: 200, json: async () => ({ a: 1 }) });
        vi.stubGlobal('fetch', fetchMock);

        const data = await http.get('/x');
        expect(data).toEqual({ a: 1 });
        const [url, opts] = fetchMock.mock.calls[0];
        expect(url).toBe('/x');
        expect(opts.credentials).toBe('same-origin');
        expect(opts.headers['X-CSRF-TOKEN']).toBe('tok123');
    });

    it('POST serializes the body + sets Content-Type', async () => {
        const fetchMock = vi.fn().mockResolvedValue({ ok: true, status: 200, json: async () => ({ ok: true }) });
        vi.stubGlobal('fetch', fetchMock);

        await http.post('/y', { name: 'z' });
        const opts = fetchMock.mock.calls[0][1];
        expect(opts.method).toBe('POST');
        expect(opts.headers['Content-Type']).toBe('application/json');
        expect(opts.body).toBe('{"name":"z"}');
    });

    it('throws HttpError with status + data on non-2xx', async () => {
        const fetchMock = vi.fn().mockResolvedValue({ ok: false, status: 422, json: async () => ({ errors: { email: ['bad'] } }) });
        vi.stubGlobal('fetch', fetchMock);

        await expect(http.post('/y', {})).rejects.toMatchObject({
            constructor: HttpError,
            status: 422,
            data: { errors: { email: ['bad'] } },
        });
    });

    it('returns null for 204 No Content', async () => {
        const fetchMock = vi.fn().mockResolvedValue({ ok: true, status: 204 });
        vi.stubGlobal('fetch', fetchMock);
        expect(await http.del('/y/1')).toBeNull();
    });
});
