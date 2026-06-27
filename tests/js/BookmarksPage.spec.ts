import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';

const h = vi.hoisted(() => ({
    post: vi.fn(),
    put: vi.fn(),
    del: vi.fn(),
    get: vi.fn(),
    confirm: vi.fn(() => Promise.resolve(true)),
    form: null as null | Record<string, unknown>,
}));

vi.mock('@inertiajs/vue3', () => ({
    router: { delete: h.del, post: h.post, get: h.get, on: vi.fn(() => vi.fn()) },
    useForm: (init: Record<string, unknown>) => {
        h.form = { ...init, post: h.post, put: h.put, reset: vi.fn(), clearErrors: vi.fn(), processing: false, errors: {} };
        return h.form;
    },
}));
vi.mock('@/composables/useConfirm', () => ({
    useConfirm: () => ({ confirm: h.confirm }),
    usePrompt: () => ({ prompt: vi.fn(() => Promise.resolve('Tools')) }),
}));

import BookmarksIndex from '@/Pages/Bookmarks/Index.vue';

const bookmarks = [
    { id: 1, title: 'Payroll', url: 'https://pay.example.com/very/long/path', description: 'HR', icon_url: null, icon_name: null, screenshot_url: null, status: 'alive', color: null, category: 'HR', shared: false, click_count: 3, can_edit: true },
    { id: 2, title: 'Company Wiki', url: 'https://wiki', description: null, icon_url: null, icon_name: null, screenshot_url: null, status: 'dead', color: null, category: 'Docs', shared: true, click_count: 9, can_edit: false },
];

beforeEach(() => Object.values(h).forEach((f) => (f as { mockClear?: () => void })?.mockClear?.()));

describe('Bookmarks/Index (3-pane)', () => {
    it('lists bookmarks and groups in the sidebar', () => {
        const wrapper = mount(BookmarksIndex, { props: { bookmarks, filters: {} } });
        expect(wrapper.text()).toContain('Payroll');
        expect(wrapper.text()).toContain('Company Wiki');
        expect(wrapper.text()).toContain('All Bookmarks');
        expect(wrapper.text()).toContain('HR');
        expect(wrapper.text()).toContain('Docs');
    });

    it('shows a detail pane with the go link when a row is selected', async () => {
        const wrapper = mount(BookmarksIndex, { props: { bookmarks, filters: {} } });
        const row = wrapper.findAll('.bm-row').find((r) => r.text().includes('Payroll'));
        await row!.trigger('click');
        const open = wrapper.findAll('a').find((a) => a.attributes('href') === '/bookmarks/1/go');
        expect(open).toBeTruthy();
    });

    it('filters the list by group', async () => {
        const wrapper = mount(BookmarksIndex, { props: { bookmarks, filters: {} } });
        // Click the "Docs" group in the sidebar.
        const docs = wrapper.findAll('.side-row').find((b) => b.text().includes('Docs'));
        await docs!.trigger('click');
        expect(wrapper.findAll('.bm-row')).toHaveLength(1);
        expect(wrapper.text()).toContain('Company Wiki');
    });

    it('creates a bookmark from the add form', async () => {
        const wrapper = mount(BookmarksIndex, { props: { bookmarks, filters: {} } });
        await wrapper.get('[title="New bookmark"]').trigger('click');
        await wrapper.findAll('button').find((b) => b.text() === 'Add')!.trigger('click');
        expect(h.post).toHaveBeenCalledWith('/bookmarks', expect.any(Object));
    });

    it('deletes the selected bookmark after confirmation', async () => {
        const wrapper = mount(BookmarksIndex, { props: { bookmarks, filters: {} } });
        // Payroll is the editable bookmark (can_edit) → its detail shows Remove.
        const row = wrapper.findAll('.bm-row').find((r) => r.text().includes('Payroll'));
        await row!.trigger('click');
        // Detail-pane "Remove" (exact) — not the "Remove duplicates" maintenance item.
        await wrapper.findAll('button').find((b) => b.text().trim() === 'Remove')!.trigger('click');
        await flushPromises();
        expect(h.confirm).toHaveBeenCalled();
        await flushPromises();
        expect(h.del).toHaveBeenCalledWith('/bookmarks/1', expect.any(Object));
    });
});
