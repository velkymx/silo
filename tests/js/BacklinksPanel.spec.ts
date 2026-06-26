import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';

const h = vi.hoisted(() => ({ get: vi.fn() }));
vi.mock('@/lib/http', () => ({ http: { get: h.get }, getText: vi.fn() }));

import BacklinksPanel from '@/Components/Notes/BacklinksPanel.vue';

beforeEach(() => h.get.mockReset());

describe('BacklinksPanel', () => {
    it('loads backlinks when noteId is set', async () => {
        h.get.mockResolvedValue({ backlinks: [{ id: 10, title: 'Ref A' }] });
        const wrapper = mount(BacklinksPanel, { props: { noteId: 1 } });
        await flushPromises();
        expect(h.get).toHaveBeenCalledWith('/notes/1/backlinks');
        expect(wrapper.text()).toContain('Ref A');
    });

    it('stale backlinks response is dropped when noteId changes before it resolves', async () => {
        let resolveFirst!: (v: unknown) => void;
        const first = new Promise((res) => { resolveFirst = res; });
        h.get
            .mockReturnValueOnce(first)
            .mockResolvedValueOnce({ backlinks: [{ id: 20, title: 'Current B' }] });

        const wrapper = mount(BacklinksPanel, { props: { noteId: 1 } });
        // Switch to note 2 before note-1 fetch resolves.
        await wrapper.setProps({ noteId: 2 });
        await flushPromises();

        // Resolve the stale note-1 response.
        resolveFirst({ backlinks: [{ id: 10, title: 'Stale A' }] });
        await flushPromises();

        expect(wrapper.text()).not.toContain('Stale A');
        expect(wrapper.text()).toContain('Current B');
    });
});
