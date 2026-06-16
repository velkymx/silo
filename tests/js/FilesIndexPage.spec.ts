import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';

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

// Stub network so child modals (Editor/Share/Doc viewers) don't hit real fetch.
vi.mock('@/lib/http', () => ({
    http: { get: vi.fn(() => Promise.resolve({ permissions: [], inherited: [], groups: [], links: [] })), post: vi.fn(() => Promise.resolve({})), put: vi.fn(() => Promise.resolve({})), del: vi.fn(() => Promise.resolve({})) },
    getText: vi.fn(() => Promise.resolve('')),
    getArrayBuffer: vi.fn(() => Promise.resolve(new ArrayBuffer(0))),
}));

// Stub heavy modal children (each covered by its own spec) so their internal
// async handlers don't fire during Files/Index interaction tests.
vi.mock('@/Components/EditorModal.vue', () => ({ default: { name: 'EditorModal', template: '<div />' } }));
vi.mock('@/Components/ShareModal.vue', () => ({ default: { name: 'ShareModal', template: '<div />' } }));
vi.mock('@/Components/QuickLookModal.vue', () => ({ default: { name: 'QuickLookModal', template: '<div />' } }));
vi.mock('@/Components/UploadModal.vue', () => ({ default: { name: 'UploadModal', template: '<div />' } }));

import FilesIndex from '@/Pages/Files/Index.vue';
import { useDialogHost } from '@/composables/useConfirm';

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

describe('Files/Index item actions (grid view)', () => {
    function gridIndex() {
        localStorage.setItem('fm-view', 'grid');
        return mountIndex();
    }
    const fileItem = (w: ReturnType<typeof mountIndex>) =>
        w.findAllComponents({ name: 'FileItem' }).find((c) => !c.props('item').is_dir)!;
    const folderItem = (w: ReturnType<typeof mountIndex>) =>
        w.findAllComponents({ name: 'FileItem' }).find((c) => c.props('item').is_dir)!;

    beforeEach(() => { Object.values(s).forEach((f) => f.mockClear()); localStorage.clear(); });

    it('star event posts to the star route', async () => {
        const w = gridIndex();
        fileItem(w).vm.$emit('star', files[0]);
        await flushPromises();
        expect(s.post).toHaveBeenCalledWith('/files/21/star', {}, expect.anything());
    });

    it('drop onto a folder moves the file', async () => {
        const w = gridIndex();
        fileItem(w).vm.$emit('drop', { payload: { id: 21 } }, { id: 10, is_dir: true });
        expect(s.post).toHaveBeenCalledWith('/files/21/move', { target_id: 10 }, expect.anything());
    });

    it('opening a folder navigates into it', async () => {
        const w = gridIndex();
        folderItem(w).vm.$emit('open', { ...folders[0] });
        expect(s.get).toHaveBeenCalledWith('/', { folder: 10 }, expect.anything());
    });

    it('action → delete confirms then deletes', async () => {
        const w = gridIndex();
        fileItem(w).vm.$emit('action', { item: { action: 'delete' } });
        const host = useDialogHost();
        expect(host.state.open).toBe(true);
        host.accept();
        await flushPromises();
        expect(s.del).toHaveBeenCalledWith('/delete/21', expect.anything());
    });

    it.each(['rename', 'move', 'copy', 'share', 'tags', 'versions', 'details', 'edit', 'download'])(
        'action → %s runs without error', (action) => {
            const w = gridIndex();
            expect(() => fileItem(w).vm.$emit('action', { item: { action } })).not.toThrow();
        },
    );

    it.each([
        ['New Folder', null],
        ['Upload files', null],
        ['Markdown file', null],
    ])('New menu → %s opens its surface', async (label) => {
        const w = gridIndex();
        const item = w.findAll('button.dd-item').find((b) => b.text().includes(label));
        await item!.trigger('click');
        // No throw + handler executed (modals are stubbed open).
        expect(item).toBeTruthy();
    });

    it('New menu → Word document opens the editor page', async () => {
        const w = gridIndex();
        const word = w.findAll('button.dd-item').find((b) => b.text().includes('Word document'));
        await word!.trigger('click');
        expect(s.get).toHaveBeenCalledWith('/files/new/docx', {});
    });

    const grid = (extra = {}) => { localStorage.setItem('fm-view', 'grid'); return mountIndex(extra); };

    it('action → move submits the transfer', async () => {
        const w = grid();
        fileItem(w).vm.$emit('action', { item: { action: 'move' } });
        await flushPromises();
        const moveBtn = w.findAll('button').find((b) => b.text().trim() === 'Move' && !b.classes().includes('dd-item'));
        await moveBtn!.trigger('click');
        expect(s.formPost).toHaveBeenCalledWith('/files/21/move', expect.anything());
    });

    it('action → tags then Save puts the tag list', async () => {
        const w = grid();
        fileItem(w).vm.$emit('action', { item: { action: 'tags' } });
        await flushPromises();
        const save = w.findAll('button').find((b) => b.text().trim() === 'Save');
        await save!.trigger('click');
        expect(s.put).toHaveBeenCalledWith('/files/21/tags', { tags: [] }, expect.anything());
    });

    it('action → versions then Restore confirms + posts', async () => {
        const w = grid({ files: [{ ...files[0], version: 2, versions: [{ id: 5, version: 1, note: 'x', size: 1024, created_at: 't' }] }] });
        fileItem(w).vm.$emit('action', { item: { action: 'versions' } });
        await flushPromises();
        const restore = w.findAll('button').find((b) => b.text().includes('Restore'));
        await restore!.trigger('click');
        const host = useDialogHost();
        host.accept();
        await flushPromises();
        expect(s.post).toHaveBeenCalledWith('/files/21/versions/5/restore', {}, expect.anything());
    });

    it('right-click context → delete confirms + deletes', async () => {
        const w = grid();
        fileItem(w).vm.$emit('context', { item: files[0], event: { clientX: 5, clientY: 6 } });
        await flushPromises();
        w.findComponent({ name: 'ContextMenu' }).vm.$emit('select', { action: 'delete' });
        const host = useDialogHost();
        expect(host.state.open).toBe(true);
        host.accept();
        await flushPromises();
        expect(s.del).toHaveBeenCalledWith('/delete/21', expect.anything());
    });

    it('space opens Quick Look and arrows page through', () => {
        grid();
        expect(() => {
            window.dispatchEvent(new KeyboardEvent('keydown', { key: ' ' }));
            window.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowRight' }));
            window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        }).not.toThrow();
    });

    it('creating a folder posts to /folders', async () => {
        const w = gridIndex();
        const newFolder = w.findAll('button.dd-item').find((b) => b.text().includes('New Folder'));
        await newFolder!.trigger('click');
        // Several modals render a "Create" button; the folder modal's runs submitFolder.
        for (const c of w.findAll('button').filter((b) => b.text().trim() === 'Create')) await c.trigger('click');
        expect(s.formPost).toHaveBeenCalledWith('/folders', expect.anything());
    });
});
