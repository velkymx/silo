// Tiny fetch wrapper: same-origin credentials, CSRF header, JSON in/out,
// throws an HttpError (with .status + .data) on non-2xx so callers can read
// Laravel validation errors.

export class HttpError extends Error {
    status: number;
    data: unknown;
    constructor(status: number, data: unknown) {
        super(`HTTP ${status}`);
        this.name = 'HttpError';
        this.status = status;
        this.data = data;
    }
}

function csrfToken(): string {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
}

async function request<T = unknown>(method: string, url: string, body?: unknown): Promise<T> {
    const headers: Record<string, string> = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-TOKEN': csrfToken(),
    };
    if (body !== undefined) headers['Content-Type'] = 'application/json';

    const res = await fetch(url, {
        method,
        headers,
        credentials: 'same-origin',
        body: body !== undefined ? JSON.stringify(body) : undefined,
    });

    const data = res.status === 204 ? null : await res.json().catch(() => null);
    if (!res.ok) throw new HttpError(res.status, data);
    return data as T;
}

export const http = {
    get: <T = unknown>(url: string) => request<T>('GET', url),
    post: <T = unknown>(url: string, body?: unknown) => request<T>('POST', url, body),
    put: <T = unknown>(url: string, body?: unknown) => request<T>('PUT', url, body),
    del: <T = unknown>(url: string) => request<T>('DELETE', url),
};
