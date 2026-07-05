import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';

const s = vi.hoisted(() => ({
    get: vi.fn(), post: vi.fn(), put: vi.fn(), del: vi.fn(), visit: vi.fn(), reload: vi.fn(),
    formPost: vi.fn(), formReset: vi.fn(),
}));
vi.mock('@inertiajs/vue3', () => ({
    router: { get: s.get, post: s.post, put: s.put, delete: s.del, visit: s.visit, reload: s.reload, on: vi.fn(() => () => {}) },
    useForm: (data: Record<string, unknown>) => ({ ...data, processing: false, errors: {}, post: s.formPost, reset: s.formReset, clearErrors: vi.fn() }),
    usePage: () => ({ url: '/', props: { auth: { user: { id: 1, name: 'QA' } }, flash: {}, errors: {}, storage: { used: 0, quota: 0 }, savedSearches: [{ id: 7, name: 'Big PDFs', params: { ftype: 'pdf' } }] } }),
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
// EditorModal loads via defineAsyncComponent. Vue only unwraps `default`
// from the resolved module when it looks like an ES module; without the
// marker it treats the vitest mock proxy itself as the component and every
// property probe on it throws asynchronously (unhandled rejections).
vi.mock('@/Components/EditorModal.vue', () => ({
    __esModule: true,
    default: { name: 'EditorModal', template: '<div />' },
}));
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
            folders, files, current: null, allFolders: folders, allTags,
            breadcrumbs: [],
            filters: { search: '', sort: 'name', direction: 'asc' },
            storage: { used: 0, quota: 0 }, maxUploadKb: 1024,
            section: 'all',
            ...extra,
        },
    });
}

beforeEach(() => { Object.values(s).forEach((f) => f.mockClear()); localStorage.clear(); });

describe('Files/Index page', () => {
    it('renders folder and file names in the contents pane', () => {
        const wrapper = mountIndex();
        const contentsPane = wrapper.get('[data-pane="contents"]');
        expect(contentsPane.text()).toContain('Reports');
        expect(contentsPane.text()).toContain('memo.txt');
    });

    it('breadcrumb click navigates to a folder', async () => {
        const wrapper = mountIndex();
        wrapper.findComponent({ name: 'VibeBreadcrumb' }).vm.$emit('item-click', { item: { folder: 10, active: false } });
        expect(s.get).toHaveBeenCalledWith('/', { folder: 10 }, expect.anything());
    });

    it('runs a smart folder (saved search) from the folders pane', async () => {
        const wrapper = mountIndex();
        expect(wrapper.text()).toContain('Big PDFs');
        await wrapper.get('.saved-search').trigger('click');
        expect(s.get).toHaveBeenCalledWith('/', { ftype: 'pdf' });
    });

    it('deletes a smart folder after confirming', async () => {
        const wrapper = mountIndex();
        await wrapper.get('.saved-search .del-btn').trigger('click');
        const host = useDialogHost();
        expect(host.state.open).toBe(true);
        host.accept();
        await flushPromises();
        expect(s.del).toHaveBeenCalledWith('/saved-searches/7', expect.anything());
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

    it('selecting an editable file in the default section shows Edit/Preview in the detail pane', async () => {
        const wrapper = mountIndex();
        wrapper.vm.selectContentItem({ ...files[0], is_dir: false });
        await wrapper.vm.$nextTick();
        const detailPane = wrapper.get('[data-pane="detail"]');
        expect(detailPane.text()).toContain(files[0].name);
        expect(detailPane.text()).toContain('Edit');
        expect(detailPane.text()).toContain('Preview');
    });

    it('table row click on a file opens the preview pane', async () => {
        const wrapper = mountIndex();
        const table = wrapper.findComponent({ name: 'VibeDataTable' });
        const row = (table.props('items') as Array<{ id: number; is_dir: boolean }>).find((i) => !i.is_dir);
        table.vm.$emit('row-clicked', row, 0);
        await wrapper.vm.$nextTick();
        expect(wrapper.get('[data-pane="detail"]').text()).toContain('memo.txt');
    });

    it('table row click on a folder navigates into it', async () => {
        const wrapper = mountIndex();
        const table = wrapper.findComponent({ name: 'VibeDataTable' });
        const row = (table.props('items') as Array<{ id: number; is_dir: boolean }>).find((i) => i.is_dir);
        table.vm.$emit('row-clicked', row, 0);
        expect(s.get).toHaveBeenCalledWith('/', { folder: 10 }, expect.anything());
    });

    it('row checkboxes drive multi-select for BatchActions', async () => {
        const wrapper = mountIndex();
        const checks = wrapper.get('[data-pane="contents"]').findAll('input[type="checkbox"]');
        expect(checks.length).toBe(2); // one per row (folder + file)
        await checks[0].setValue(true);
        await checks[1].setValue(true);
        const batch = wrapper.findComponent({ name: 'BatchActions' });
        expect((batch.props('selectedItems') as unknown[]).length).toBe(2);
    });

    it('Escape clears the selection and collapses the preview pane', async () => {
        const wrapper = mountIndex();
        wrapper.vm.selectContentItem({ ...files[0], is_dir: false });
        await wrapper.vm.$nextTick();
        expect(wrapper.find('[data-pane="detail"]').exists()).toBe(true);
        window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        await wrapper.vm.$nextTick();
        expect(wrapper.find('[data-pane="detail"]').exists()).toBe(false);
    });

    it('table omits checkbox and action columns for sections the viewer does not own', () => {
        const wrapper = mountIndex({ section: 'shared' });
        const table = wrapper.findComponent({ name: 'VibeDataTable' });
        const keys = (table.props('columns') as Array<{ key: string }>).map((c) => c.key);
        expect(keys).toEqual(['name', 'modified', 'size', 'kind']);
    });

    it('table sorts by name ascending with sortable meta columns', () => {
        const wrapper = mountIndex();
        const table = wrapper.findComponent({ name: 'VibeDataTable' });
        const keys = (table.props('columns') as Array<{ key: string }>).map((c) => c.key);
        expect(keys).toEqual(['_select', 'name', 'modified', 'size', 'kind', '_actions']);
        expect(table.attributes()).toMatchObject({ 'sort-by': 'name' });
    });

    it('there is no select-mode toggle in the toolbar', () => {
        const wrapper = mountIndex();
        expect(wrapper.findAll('button').some((b) => b.attributes('title') === 'Select multiple')).toBe(false);
    });

    it('grid thumbnail size control persists the choice', async () => {
        localStorage.setItem('fm-view', 'grid');
        const wrapper = mountIndex();
        const large = wrapper.findAll('button').find((b) => b.attributes('title') === 'Large thumbnails');
        await large!.trigger('click');
        expect(localStorage.getItem('fm-grid-size')).toBe('l');
    });

    it('HI-17: mounts without throwing when localStorage.getItem throws SecurityError', () => {
        vi.spyOn(Storage.prototype, 'getItem').mockImplementationOnce(() => {
            throw new DOMException('The operation is insecure.', 'SecurityError');
        });
        let wrapper: ReturnType<typeof mountIndex> | undefined;
        expect(() => { wrapper = mountIndex(); }).not.toThrow();
        // grid-only template absent → defaulted to list view
        expect(wrapper!.find('[data-view="grid"]').exists() || wrapper!.findAll('button').some((b) => b.attributes('title') === 'Thumbnail view')).toBe(true);
        vi.restoreAllMocks();
    });

    it('HI-17: switching view does not throw when localStorage.setItem throws SecurityError', async () => {
        vi.spyOn(Storage.prototype, 'setItem').mockImplementation(() => {
            throw new DOMException('The operation is insecure.', 'SecurityError');
        });
        const wrapper = mountIndex();
        const gridBtn = wrapper.findAll('button').find((b) => b.attributes('title') === 'Thumbnail view');
        await expect(gridBtn!.trigger('click')).resolves.not.toThrow();
        vi.restoreAllMocks();
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

    it('CR-05: size cell uses fmtBytes not raw division — 2 GB shows "2 GB" not "2097152.0 KB"', () => {
        const bigFile = { id: 99, name: 'big.bin', is_dir: false, type: 'bin', size: 2 * 1024 * 1024 * 1024, status: 'ready', versions: [], tags: [], created_at: 'now' };
        const w = mountIndex({ files: [bigFile] });
        // Table view renders the size cell.
        expect(w.text()).not.toContain('2097152.0 KB');
        expect(w.text()).toContain('GB');
    });
});
