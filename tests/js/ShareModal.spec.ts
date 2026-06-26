import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';

const { httpGet, httpPost, httpDel } = vi.hoisted(() => ({ httpGet: vi.fn(), httpPost: vi.fn(), httpDel: vi.fn() }));
vi.mock('@/lib/http', () => ({
    http: { get: httpGet, post: httpPost, del: httpDel },
    HttpError: class extends Error { status = 0; data: unknown = null; },
}));

import ShareModal from '@/Components/ShareModal.vue';

const file = { id: 5, name: 'plan.pdf', is_dir: false };

function seed() {
    httpGet.mockImplementation((url: string) =>
        url.includes('/permissions')
            ? Promise.resolve({ permissions: [{ id: 1, subject_type: 'user', subject_label: 'a@b.c', ability: 'read' }], inherited: [], groups: [{ id: 2, name: 'Staff' }] })
            : Promise.resolve({ links: [{ id: 9, url: 'http://x/s/abc', allow_download: true }] }),
    );
}

describe('ShareModal', () => {
    beforeEach(() => { httpGet.mockReset(); httpPost.mockReset(); httpDel.mockReset(); seed(); });

    it('loads grants + links when opened', async () => {
        const wrapper = mount(ShareModal, { props: { modelValue: false, item: file } });
        await wrapper.setProps({ modelValue: true });
        await flushPromises();
        expect(httpGet).toHaveBeenCalledWith('/files/5/permissions');
        expect(httpGet).toHaveBeenCalledWith('/files/5/links');
        expect(wrapper.text()).toContain('a@b.c');
        expect(wrapper.text()).toContain('http://x/s/abc');
    });

    it('Grant posts the new permission', async () => {
        httpPost.mockResolvedValue({ permissions: [] });
        const wrapper = mount(ShareModal, { props: { modelValue: true, item: file } });
        await flushPromises();
        const btn = wrapper.findAll('button').find((b) => b.text() === 'Grant');
        await btn!.trigger('click');
        expect(httpPost).toHaveBeenCalledWith('/files/5/permissions', expect.objectContaining({ subject_type: 'user' }));
    });

    it('removing a grant deletes it', async () => {
        httpDel.mockResolvedValue({ permissions: [] });
        const wrapper = mount(ShareModal, { props: { modelValue: false, item: file } });
        await wrapper.setProps({ modelValue: true });
        await flushPromises();
        // The grants table is the first one; its row has a single remove button.
        await wrapper.find('table tbody tr button').trigger('click');
        expect(httpDel).toHaveBeenCalledWith('/files/5/permissions/1');
    });

    it('has a Cancel button that closes the modal', async () => {
        const wrapper = mount(ShareModal, { props: { modelValue: true, item: file } });
        await flushPromises();
        const cancel = wrapper.findAll('button').find((b) => b.text() === 'Cancel');
        expect(cancel).toBeTruthy();
        await cancel!.trigger('click');
        expect(wrapper.emitted('update:modelValue')!.at(-1)).toEqual([false]);
    });

    it('clears the copied timer on unmount (no leak)', async () => {
        Object.assign(navigator, { clipboard: { writeText: vi.fn() } });
        const wrapper = mount(ShareModal, { props: { modelValue: false, item: file } });
        await wrapper.setProps({ modelValue: true });
        await flushPromises();
        vi.useFakeTimers();
        // Links table is the last table; its first row button is "copy".
        const tables = wrapper.findAll('table');
        const copy = tables[tables.length - 1].findAll('button')[0];
        await copy.trigger('click');
        wrapper.unmount();
        // Flushing the pending 1.5s timeout after unmount must not throw / update state.
        expect(() => vi.runAllTimers()).not.toThrow();
        vi.useRealTimers();
    });

    it('creating a public link posts the link form', async () => {
        httpPost.mockResolvedValue({ links: [] });
        const wrapper = mount(ShareModal, { props: { modelValue: true, item: file } });
        await flushPromises();
        const btn = wrapper.findAll('button').find((b) => b.text().includes('Create link'));
        await btn!.trigger('click');
        expect(httpPost).toHaveBeenCalledWith('/files/5/links', expect.objectContaining({ allow_download: true }));
    });

    it('stale load response is dropped when modal is closed and re-opened', async () => {
        httpGet.mockReset();
        let resolveStale!: (v: unknown) => void;
        const stalePerms = new Promise((res) => { resolveStale = res; });
        // First open: slow permissions fetch, fast links.
        httpGet
            .mockReturnValueOnce(stalePerms)
            .mockResolvedValueOnce({ links: [] })
            // Second open: fast permissions + links.
            .mockResolvedValueOnce({ permissions: [{ id: 2, subject_type: 'user', subject_label: 'current@b.c', ability: 'read' }], inherited: [], groups: [] })
            .mockResolvedValueOnce({ links: [] });

        const wrapper = mount(ShareModal, { props: { modelValue: false, item: file } });
        await wrapper.setProps({ modelValue: true });   // first open — stale perms stalls
        await wrapper.setProps({ modelValue: false });  // close
        await wrapper.setProps({ modelValue: true });   // re-open — fast perms
        await flushPromises();

        // Resolve the stale response after the current one already landed.
        resolveStale({ permissions: [{ id: 1, subject_type: 'user', subject_label: 'stale@b.c', ability: 'read' }], inherited: [], groups: [] });
        await flushPromises();

        expect(wrapper.text()).not.toContain('stale@b.c');
        expect(wrapper.text()).toContain('current@b.c');
    });
});
