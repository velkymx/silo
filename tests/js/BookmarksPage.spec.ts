import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';

const h = vi.hoisted(() => ({
    post: vi.fn(),
    put: vi.fn(),
    del: vi.fn(),
    confirm: vi.fn(() => Promise.resolve(true)),
    form: null as null | Record<string, unknown>,
}));

vi.mock('@inertiajs/vue3', () => ({
    router: { delete: h.del },
    useForm: (init: Record<string, unknown>) => {
        h.form = { ...init, post: h.post, put: h.put, reset: vi.fn(), clearErrors: vi.fn(), processing: false, errors: {} };
        return h.form;
    },
}));
vi.mock('@/composables/useConfirm', () => ({ useConfirm: () => ({ confirm: h.confirm }) }));

import BookmarksIndex from '@/Pages/Bookmarks/Index.vue';

const bookmarks = [
    { id: 1, title: 'Payroll', url: 'https://pay', description: 'HR', icon: 'cash', color: null, category: 'HR', shared: false, click_count: 3, can_edit: true },
    { id: 2, title: 'Company Wiki', url: 'https://wiki', description: null, icon: 'book', color: null, category: 'Docs', shared: true, click_count: 9, can_edit: false },
];

beforeEach(() => {
    h.post.mockClear();
    h.put.mockClear();
    h.del.mockClear();
});

describe('Bookmarks/Index', () => {
    it('renders bookmarks grouped by category', () => {
        const wrapper = mount(BookmarksIndex, { props: { bookmarks } });
        expect(wrapper.text()).toContain('Payroll');
        expect(wrapper.text()).toContain('Company Wiki');
        expect(wrapper.text()).toContain('HR');
        expect(wrapper.text()).toContain('Docs');
    });

    it('links each bookmark through the click-counting go route', () => {
        const wrapper = mount(BookmarksIndex, { props: { bookmarks } });
        const hrefs = wrapper.findAll('a').map((a) => a.attributes('href'));
        expect(hrefs).toContain('/bookmarks/1/go');
    });

    it('only shows edit controls on editable bookmarks', () => {
        const wrapper = mount(BookmarksIndex, { props: { bookmarks } });
        // One card is editable → one set of pencil/trash actions.
        expect(wrapper.findAll('[title="Edit"]')).toHaveLength(1);
    });

    it('creates a bookmark via the add form', async () => {
        const wrapper = mount(BookmarksIndex, { props: { bookmarks: [] } });
        await wrapper.findAll('button').find((b) => b.text().includes('Add'))!.trigger('click');
        await wrapper.findAll('button').find((b) => b.text() === 'Add')!.trigger('click');
        expect(h.post).toHaveBeenCalledWith('/bookmarks', expect.any(Object));
    });

    it('deletes a bookmark after confirmation', async () => {
        const wrapper = mount(BookmarksIndex, { props: { bookmarks } });
        await wrapper.get('[title="Remove"]').trigger('click');
        await Promise.resolve();
        expect(h.confirm).toHaveBeenCalled();
        await Promise.resolve();
        expect(h.del).toHaveBeenCalledWith('/bookmarks/1', expect.any(Object));
    });
});
