import { ref, reactive, computed } from 'vue';
import { csrfToken } from '../lib/http';

// Wraps XMLHttpRequest in a Vue-native upload state machine. The caller gets a
// reactive `items` array (one entry per file) and start/cancel/retry/clear
// methods. Errors propagate via item.error; the caller decides UX.
//
// Items are deep-reactive so per-field mutations (state, progress) re-render
// the queue without the consumer having to re-assign the array.

export interface UploadItem {
    id: number;
    file: File;
    state: 'pending' | 'uploading' | 'done' | 'error' | 'cancelled';
    progress: number;
    error: string;
    controller: AbortController | null;
    retries: number;
}

export interface UploadOptions {
    url: string;
    parentId: number | null;
    maxRetries?: number;
    autoStart?: boolean;
    /** Per-file cap in KB. Files larger than this are marked 'error' on enqueue. */
    maxSizeKb?: number;
}

export function useFileUpload(opts: UploadOptions) {
    const items = reactive<UploadItem[]>([]);
    const blobUrls = new Map<File, string>();
    let seq = 0;
    const fileIds = new WeakMap<File, number>();
    const maxRetries = opts.maxRetries ?? 2;
    const autoStart = opts.autoStart ?? true;

    function nextId(f: File): number {
        if (!fileIds.has(f)) fileIds.set(f, ++seq);
        return fileIds.get(f)!;
    }

    function blobUrlFor(f: File): string | null {
        if (!f?.type?.startsWith('image/')) return null;
        const existing = blobUrls.get(f);
        if (existing) return existing;
        try {
            const url = window.URL.createObjectURL(f);
            blobUrls.set(f, url);
            return url;
        } catch { return null; }
    }

    function revokeBlobUrl(f: File): void {
        const url = blobUrls.get(f);
        if (url) {
            try { window.URL.revokeObjectURL(url); } catch { /* ignore */ }
            blobUrls.delete(f);
        }
    }

    function startUpload(item: UploadItem): void {
        const form = new FormData();
        form.append('files[]', item.file);
        if (opts.parentId != null) form.append('parent_id', String(opts.parentId));

        const controller = new AbortController();
        item.controller = controller;
        item.state = 'uploading';
        item.progress = 0;
        item.error = '';

        const xhr = new XMLHttpRequest();
        xhr.open('POST', opts.url);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('Accept', 'application/json');
        xhr.setRequestHeader('X-CSRF-TOKEN', csrfToken());
        xhr.upload.onprogress = (e) => {
            if (e.lengthComputable) item.progress = Math.round((e.loaded / e.total) * 100);
        };
        xhr.onload = () => {
            if (xhr.status >= 200 && xhr.status < 300) {
                item.state = 'done';
                item.progress = 100;
            } else if (controller.signal.aborted) {
                item.state = 'cancelled';
            } else {
                item.state = 'error';
                item.error = `Upload failed (${xhr.status})`;
            }
            item.controller = null;
        };
        xhr.onerror = () => { item.state = 'error'; item.error = 'Network error'; item.controller = null; };
        xhr.onabort = () => { item.state = 'cancelled'; item.controller = null; };
        xhr.send(form);
    }

    function enqueue(list: FileList | File[]): void {
        const incoming = Array.from(list);
        for (const file of incoming) blobUrlFor(file);
        for (const file of incoming) {
            const tooBig = opts.maxSizeKb && file.size > opts.maxSizeKb * 1024;
            const item: UploadItem = reactive({
                id: nextId(file),
                file,
                state: tooBig ? 'error' : 'pending',
                progress: 0,
                error: tooBig ? `Exceeds ${opts.maxSizeKb} KB per-file limit` : '',
                controller: null,
                retries: 0,
            });
            items.push(item);
            if (autoStart && !tooBig) startUpload(item);
        }
    }

    function cancel(id: number): void {
        const it = items.find(i => i.id === id);
        if (!it) return;
        if (it.state === 'uploading') it.controller?.abort();
        else if (it.state === 'pending') it.state = 'cancelled';
    }

    function retry(id: number): void {
        const it = items.find(i => i.id === id);
        if (!it || it.retries >= maxRetries) return;
        it.retries += 1;
        startUpload(it);
    }

    function remove(id: number): void {
        const idx = items.findIndex(i => i.id === id);
        if (idx === -1) return;
        const it = items[idx];
        if (it.state === 'uploading') it.controller?.abort();
        revokeBlobUrl(it.file);
        items.splice(idx, 1);
    }

    function clear(): void {
        for (const it of items) {
            if (it.state === 'uploading') it.controller?.abort();
            revokeBlobUrl(it.file);
        }
        items.splice(0, items.length);
    }

    const totalBytes = computed(() => items.reduce((s, i) => s + i.file.size, 0));
    const activeCount = computed(() => items.filter(i => i.state === 'uploading' || i.state === 'pending').length);
    const doneCount = computed(() => items.filter(i => i.state === 'done').length);
    const errorCount = computed(() => items.filter(i => i.state === 'error').length);

    return {
        items,
        totalBytes,
        activeCount,
        doneCount,
        errorCount,
        enqueue,
        cancel,
        retry,
        remove,
        clear,
        blobUrlFor,
    };
}
