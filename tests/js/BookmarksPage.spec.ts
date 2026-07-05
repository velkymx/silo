import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises, VueWrapper } from '@vue/test-utils';

const h = vi.hoisted(() => ({
    post: vi.fn(),
    put: vi.fn(),
    del: vi.fn(),
    get: vi.fn(),
    confirm: vi.fn(() => Promise.resolve(true)),
    form: null as null | Record<string, unknown>,
}));

vi.mock('@inertiajs/vue3', () => ({
    router: { delete: h.del, post: h.post, get: h.get, visit: vi.fn(), on: vi.fn(() => vi.fn()) },
    useForm: (init: Record<string, unknown>) => {
        h.form = { ...init, post: h.post, put: h.put, reset: vi.fn(), clearErrors: vi.fn(), processing: false, errors: {} };
        return h.form;
    },
    usePage: () => ({ url: '/bookmarks', props: { auth: { user: { id: 1, name: 'QA' } }, storage: { used: 0, quota: 0 } } }),
    Link: { name: 'Link', template: '<a><slot /></a>' },
}));
vi.mock('@/composables/useConfirm', () => ({
    useConfirm: () => ({ confirm: h.confirm }),
    usePrompt: () => ({ prompt: vi.fn(() => Promise.resolve('Tools')) }),
    useDialogHost: () => ({
        state: { open: false, mode: 'confirm', title: '', message: '', inputValue: '', placeholder: '', confirmLabel: 'OK', cancelLabel: 'Cancel', variant: 'primary' },
        accept: vi.fn(),
        cancel: vi.fn(),
    }),
}));

import BookmarksIndex from '@/Pages/Bookmarks/Index.vue';

const bookmarks = [
    { id: 1, title: 'Payroll', url: 'https://pay.example.com/very/long/path', description: 'HR', icon_url: null, icon_name: null, screenshot_url: null, status: 'alive', color: null, category: 'HR', shared: false, click_count: 3, can_edit: true },
    { id: 2, title: 'Company Wiki', url: 'https://wiki', description: null, icon_url: null, icon_name: null, screenshot_url: null, status: 'dead', color: null, category: 'Docs', shared: true, click_count: 9, can_edit: false },
];

function selectRow(wrapper: VueWrapper, id: number) {
    const table = wrapper.findComponent({ name: 'VibeDataTable' });
    const row = (table.props('items') as Array<{ id: number }>).find((b) => b.id === id);
    table.vm.$emit('row-clicked', row, 0);
}

beforeEach(() => Object.values(h).forEach((f) => (f as { mockClear?: () => void })?.mockClear?.()));

describe('Bookmarks/Index (explorer shell)', () => {
    it('lists bookmarks in the table and folders in the sidebar', () => {
        const wrapper = mount(BookmarksIndex, { props: { bookmarks, filters: {} } });
        expect(wrapper.findComponent({ name: 'VibeDataTable' }).exists()).toBe(true);
        expect(wrapper.text()).toContain('Payroll');
        expect(wrapper.text()).toContain('Company Wiki');
        // The tree roots at the section itself (id 0 = "Bookmarks").
        expect(wrapper.find('[data-folder="0"]').text()).toContain('Bookmarks');
        expect(wrapper.text()).toContain('HR');
        expect(wrapper.text()).toContain('Docs');
    });

    it('collapses the detail pane until a row is selected, then shows the go link', async () => {
        const wrapper = mount(BookmarksIndex, { props: { bookmarks, filters: {} } });
        expect(wrapper.find('[data-pane="detail"]').exists()).toBe(false);
        selectRow(wrapper, 1);
        await wrapper.vm.$nextTick();
        expect(wrapper.find('[data-pane="detail"]').exists()).toBe(true);
        const open = wrapper.findAll('a').find((a) => a.attributes('href') === '/bookmarks/1/go');
        expect(open).toBeTruthy();
    });

    it('filters the table by accordion folder', async () => {
        const wrapper = mount(BookmarksIndex, { props: { bookmarks, filters: {} } });
        const docs = wrapper.findAll('[data-folder]').find((b) => b.text().includes('Docs'));
        await docs!.trigger('click');
        const table = wrapper.findComponent({ name: 'VibeDataTable' });
        const titles = (table.props('items') as Array<{ title: string }>).map((b) => b.title);
        expect(titles).toEqual(['Company Wiki']);
    });

    it('creates a bookmark from the add form', async () => {
        const wrapper = mount(BookmarksIndex, { props: { bookmarks, filters: {} } });
        await wrapper.get('[title="New bookmark"]').trigger('click');
        await wrapper.findAll('button').find((b) => b.text() === 'Add')!.trigger('click');
        expect(h.post).toHaveBeenCalledWith('/bookmarks', expect.any(Object));
    });

    it('deletes the selected bookmark after confirmation', async () => {
        const wrapper = mount(BookmarksIndex, { props: { bookmarks, filters: {} } });
        // Payroll is the editable bookmark (can_edit) → its detail shows Delete.
        selectRow(wrapper, 1);
        await wrapper.vm.$nextTick();
        // Detail-pane "Delete" (exact) — not the "Delete duplicates" maintenance item.
        const detail = wrapper.get('[data-pane="detail"]');
        await detail.findAll('button').find((b) => b.text().trim() === 'Delete')!.trigger('click');
        await flushPromises();
        expect(h.confirm).toHaveBeenCalled();
        await flushPromises();
        expect(h.del).toHaveBeenCalledWith('/bookmarks/1', expect.any(Object));
    });

    it('checkbox multi-select surfaces the bulk-delete bar', async () => {
        const wrapper = mount(BookmarksIndex, { props: { bookmarks, filters: {} } });
        const checks = wrapper.findAll('.bm-select-check input[type="checkbox"], input[type="checkbox"]');
        await checks[0].setValue(true);
        expect(wrapper.text()).toContain('1 selected');
    });
});
