import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';

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

const routerReload = vi.hoisted(() => vi.fn());
vi.mock('@inertiajs/vue3', () => ({ router: { reload: routerReload } }));
vi.mock('@/lib/http', () => ({ csrfToken: () => 'test-csrf' }));

import UploadModal from '@/Components/UploadModal.vue';

const png = () => new File(['x'], 'pic.png', { type: 'image/png' });

describe('UploadModal', () => {
    beforeEach(() => routerReload.mockClear());

    it('shows the per-file size cap label', () => {
        const wrapper = mount(UploadModal, { props: { modelValue: true, parentId: 1, maxUploadKb: 2048 } as never });
        expect(wrapper.text()).toContain('2 MB');
    });

    it('dropping files lists them and auto-starts an XHR per file', async () => {
        const wrapper = mount(UploadModal, { props: { modelValue: true, parentId: null, maxUploadKb: 1024 } as never });
        await wrapper.find('.upload-dropzone').trigger('drop', { dataTransfer: { files: [png()] } });
        expect(wrapper.text()).toContain('pic.png');
        expect(wrapper.text()).toContain('0/1 uploaded');
        expect(FakeXHR.instances).toHaveLength(1);
    });

    it('removing a completed file drops it from the list', async () => {
        const wrapper = mount(UploadModal, { props: { modelValue: true, parentId: null, maxUploadKb: 1024 } as never });
        await wrapper.find('.upload-dropzone').trigger('drop', { dataTransfer: { files: [png()] } });
        const xhr = FakeXHR.last!;
        xhr.status = 200;
        xhr.onload?.();
        await flushPromises();
        const xBtn = wrapper.findAll('button').find((b) => (b.attributes('aria-label') ?? '').startsWith('Remove '));
        expect(xBtn).toBeDefined();
        await xBtn!.trigger('click');
        expect(wrapper.text()).not.toContain('pic.png');
    });

    it('does not mutate the native File object with a __url property', async () => {
        const file = png();
        const wrapper = mount(UploadModal, { props: { modelValue: true, parentId: null, maxUploadKb: 1024 } as never });
        await wrapper.find('.upload-dropzone').trigger('drop', { dataTransfer: { files: [file] } });
        expect('__url' in file).toBe(false);
        expect((file as unknown as Record<string, unknown>).__url).toBeUndefined();
    });

    it('blocks the dropzone when the selection exceeds quota', async () => {
        const big = new File([new Uint8Array(2000)], 'big.bin', { type: 'application/octet-stream' });
        const wrapper = mount(UploadModal, {
            props: { modelValue: true, parentId: null, maxUploadKb: 99999, storage: { used: 0, quota: 1000 } } as never,
        });
        await wrapper.find('.upload-dropzone').trigger('drop', { dataTransfer: { files: [big] } });
        expect(wrapper.text()).toContain('exceed your storage quota');
    });
});
