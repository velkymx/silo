import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';

const s = vi.hoisted(() => ({
    get: vi.fn(), post: vi.fn(), put: vi.fn(), del: vi.fn(), visit: vi.fn(), reload: vi.fn(),
    formPost: vi.fn(), formReset: vi.fn(),
}));
vi.mock('@inertiajs/vue3', () => ({
    router: { get: s.get, post: s.post, put: s.put, delete: s.del, visit: s.visit, reload: s.reload, on: vi.fn(() => () => {}) },
    useForm: (data: Record<string, unknown>) => ({ ...data, processing: false, errors: {}, post: s.formPost, reset: s.formReset, clearErrors: vi.fn() }),
    usePage: () => ({ props: { auth: { user: { id: 1, name: 'QA' } }, flash: {}, errors: {}, storage: { used: 0, quota: 0 } } }),
    Link: { name: 'Link', template: '<a><slot /></a>' },
    Head: { name: 'Head', template: '<span><slot /></span>' },
}));

import FilesIndex from '@/Pages/Files/Index.vue';

const folders = [{ id: 10, name: 'Reports', is_dir: true, item_count: 0, tags: [], updated_at: 'now' }];
const files = [{ id: 21, name: 'memo.txt', is_dir: false, type: 'txt', size: 12, status: 'ready', versions: [], tags: [], created_at: 'now' }];
const allTags = [{ id: 3, name: 'work', color: '#abc' }];

function mountIndex(extra = {}) {
    return mount(FilesIndex, {
        props: {
            folders, files, allFolders: folders, allTags,
            breadcrumbs: [],
            filters: { search: '', sort: 'name', direction: 'asc' },
            storage: { used: 0, quota: 0 }, maxUploadKb: 1024,
            ...extra,
        },
    });
}

beforeEach(() => { Object.values(s).forEach((f) => f.mockClear()); localStorage.clear(); });

describe('Files/Index page', () => {
    it('renders folder and file names', () => {
        const wrapper = mountIndex();
        expect(wrapper.text()).toContain('Reports');
        expect(wrapper.text()).toContain('memo.txt');
    });

    it('breadcrumb click navigates to a folder', async () => {
        const wrapper = mountIndex();
        wrapper.findComponent({ name: 'VibeBreadcrumb' }).vm.$emit('item-click', { item: { folder: 10, active: false } });
        expect(s.get).toHaveBeenCalledWith('/', { folder: 10 }, expect.anything());
    });

    it('clicking a sidebar tag filters by it', async () => {
        const wrapper = mountIndex();
        const tag = wrapper.findAll('.badge').find((b) => b.text() === 'work');
        await tag!.trigger('click');
        expect(s.get).toHaveBeenCalledWith('/', { tag: 3 }, expect.anything());
    });

    it('shows a search chip and "Clear all" resets filters', async () => {
        const wrapper = mountIndex({ searching: true, flat: true, filters: { search: 'report', sort: 'name', direction: 'asc' } });
        expect(wrapper.text()).toContain('Search: "report"');
        const clearAll = wrapper.findAll('button').find((b) => b.text().trim() === 'Clear all');
        await clearAll!.trigger('click');
        expect(s.get).toHaveBeenCalledWith('/', {}, expect.anything());
    });

    it('switching to grid view persists the preference', async () => {
        const wrapper = mountIndex();
        const grid = wrapper.findAll('button').find((b) => b.attributes('title') === 'Thumbnail view');
        await grid!.trigger('click');
        expect(localStorage.getItem('fm-view')).toBe('grid');
    });

    it('shows the advanced-filter summary chip', () => {
        const wrapper = mountIndex({
            searching: true, advanced: true, flat: true,
            filters: { search: '', sort: 'name', direction: 'asc', ftype: 'image', size_min: 1048576 },
        });
        expect(wrapper.text()).toContain('Images');
    });
});
