import { describe, it, expect } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { router } from '@inertiajs/vue3';
import { vi } from 'vitest';
import { useDialogHost } from '@/composables/useConfirm';

// Files now hosts on ShellLayout, which renders the real GlobalRail (not
// stubbed in tests/js/setup.ts). GlobalRail reads `usePage().url` — override
// just that export so it doesn't blow up on the module-level "no page yet"
// state, while keeping the real `router` so `vi.spyOn(router, ...)` below
// still works against the genuine module.
vi.mock('@inertiajs/vue3', async (importOriginal) => {
    const actual = await importOriginal<typeof import('@inertiajs/vue3')>();
    return {
        ...actual,
        usePage: () => ({ url: '/', props: { auth: { user: { id: 1, is_admin: false } } } }),
    };
});

import FilesIndex from '@/Pages/Files/Index.vue';

const base = {
    folders: [], files: [], current: null, breadcrumbs: [], allFolders: [],
    allTags: [], storage: { used: 0, quota: 0 }, filters: { search: '', sort: 'name', direction: 'asc' },
    section: 'all',
};

describe('Files shell', () => {
    it('renders the four-pane shell with GlobalRail in column 1 and the section rail in column 2', () => {
        const wrapper = mount(FilesIndex, { props: base });
        expect(wrapper.findComponent({ name: 'FourPane' }).exists()).toBe(true);

        // Column 1 (rail pane) is the universal GlobalRail app-nav now —
        // the section rail no longer lives there.
        const railPane = wrapper.get('[data-pane="rail"]');
        expect(railPane.find('[data-nav]').exists()).toBe(true);
        expect(railPane.find('[data-section]').exists()).toBe(false);

        // Column 2 (folders/viewNav pane) hosts the section rail instead.
        expect(wrapper.findComponent({ name: 'SectionRail' }).exists()).toBe(true);
        const foldersPane = wrapper.get('[data-pane="folders"]');
        expect(foldersPane.find('[data-section="all"]').exists()).toBe(true);
        expect(foldersPane.find('[data-section="trash"]').exists()).toBe(true);
    });

    it('navigates to a folder from the accordion', async () => {
        const spy = vi.spyOn(router, 'get').mockImplementation(() => {});
        const wrapper = mount(FilesIndex, { props: { ...base, allFolders: [{ id: 5, name: 'Docs', parent_id: null }] } });
        await wrapper.get('[data-folder="5"]').trigger('click');
        expect(spy).toHaveBeenCalledWith('/', { folder: 5 }, { preserveScroll: true });
        spy.mockRestore();
    });

    it('lists folder contents in a searchable DataTable', () => {
        const wrapper = mount(FilesIndex, { props: { ...base, files: [{ id: 9, name: 'a.md', type: 'md', size: 10, created_at: '2026-01-01' }] } });
        const dt = wrapper.findComponent({ name: 'VibeDataTable' });
        expect(dt.exists()).toBe(true);
        expect(dt.props('searchable')).toBe(true);
    });

    it('shows Restore/Delete forever in the detail pane for trash section', async () => {
        const wrapper = mount(FilesIndex, {
            props: { ...base, section: 'trash', files: [{ id: 3, name: 'x.md', type: 'md', size: 10, created_at: '2026-01-01', deleted_at: '2026-01-01' }] },
        });
        wrapper.vm.selectContentItem({ id: 3, name: 'x.md', type: 'md', is_dir: false });
        await wrapper.vm.$nextTick();
        const detailPane = wrapper.get('[data-pane="detail"]');
        expect(detailPane.text()).toContain('Restore');
        expect(detailPane.text()).toContain('Delete forever');
    });

    it('marks the detail pane read-only for a shared item without write ability', async () => {
        const wrapper = mount(FilesIndex, {
            props: { ...base, section: 'shared', files: [{ id: 4, name: 'y.md', type: 'md', size: 10, created_at: '2026-01-01', abilities: ['read'] }] },
        });
        wrapper.vm.selectContentItem({ id: 4, name: 'y.md', type: 'md', is_dir: false, abilities: ['read'] });
        await wrapper.vm.$nextTick();
        const detailPane = wrapper.get('[data-pane="detail"]');
        expect(detailPane.text()).not.toContain('Edit');
        expect(detailPane.text()).toContain('Read-only');
    });

    it('navigates via router.get when a rail section is clicked', async () => {
        const spy = vi.spyOn(router, 'get').mockImplementation(() => {});
        const wrapper = mount(FilesIndex, { props: base });

        await wrapper.get('[data-section="starred"]').trigger('click');
        expect(spy).toHaveBeenCalledWith('/', { section: 'starred' }, { preserveScroll: true, preserveState: false });

        await wrapper.get('[data-section="all"]').trigger('click');
        expect(spy).toHaveBeenCalledWith('/', {}, { preserveScroll: true, preserveState: false });

        spy.mockRestore();
    });

    it('shows the folder accordion only for the "all" section', () => {
        const wrapper = mount(FilesIndex, {
            props: { ...base, section: 'all', allFolders: [{ id: 5, name: 'Docs', parent_id: null }] },
        });
        expect(wrapper.get('[data-pane="folders"]').find('[data-folder]').exists()).toBe(true);
    });

    it('hides the folder accordion for flat sections like trash', () => {
        const wrapper = mount(FilesIndex, {
            props: { ...base, section: 'trash', allFolders: [{ id: 5, name: 'Docs', parent_id: null }] },
        });
        expect(wrapper.get('[data-pane="folders"]').find('[data-folder]').exists()).toBe(false);
    });

    it('shows an empty state in the contents pane when a section has no items', () => {
        localStorage.setItem('fm-view', 'grid');
        const wrapper = mount(FilesIndex, { props: { ...base, section: 'trash', files: [], folders: [] } });
        localStorage.removeItem('fm-view');
        const contentsPane = wrapper.get('[data-pane="contents"]');
        expect(contentsPane.findComponent({ name: 'EmptyState' }).exists()).toBe(true);
        expect(contentsPane.text()).toContain('This folder is empty');
    });

    // C1: selectContentItem must be section-aware — folder rows in shared/trash
    // don't live under the owner's `/` tree, so visitFolder(id) 404s for them.
    describe('selectContentItem is section-aware', () => {
        it('trash: always opens the detail pane, even for a folder (no navigation)', async () => {
            const spy = vi.spyOn(router, 'get').mockImplementation(() => {});
            const wrapper = mount(FilesIndex, {
                props: { ...base, section: 'trash', folders: [{ id: 7, name: 'Old Docs', is_dir: true, deleted_at: '2026-01-01' }] },
            });
            wrapper.vm.selectContentItem({ id: 7, name: 'Old Docs', is_dir: true });
            await wrapper.vm.$nextTick();
            const detailPane = wrapper.get('[data-pane="detail"]');
            expect(detailPane.text()).toContain('Restore');
            expect(spy).not.toHaveBeenCalled();
            spy.mockRestore();
        });

        it('shared: a folder navigates to the shared-folder browse route', () => {
            const spy = vi.spyOn(router, 'get').mockImplementation(() => {});
            const wrapper = mount(FilesIndex, { props: { ...base, section: 'shared' } });
            wrapper.vm.selectContentItem({ id: 7, is_dir: true });
            expect(spy).toHaveBeenCalledWith('/shared/7', {}, { preserveScroll: true });
            spy.mockRestore();
        });

        it('shared: a file still opens the detail pane', async () => {
            const wrapper = mount(FilesIndex, {
                props: { ...base, section: 'shared', files: [{ id: 4, name: 'y.md', type: 'md', size: 10, created_at: '2026-01-01', abilities: ['read'] }] },
            });
            wrapper.vm.selectContentItem({ id: 4, name: 'y.md', type: 'md', is_dir: false, abilities: ['read'] });
            await wrapper.vm.$nextTick();
            expect(wrapper.get('[data-pane="detail"]').text()).toContain('y.md');
        });

        it('all: a folder still navigates via router.get to "/"', () => {
            const spy = vi.spyOn(router, 'get').mockImplementation(() => {});
            const wrapper = mount(FilesIndex, { props: { ...base, section: 'all' } });
            wrapper.vm.selectContentItem({ id: 7, is_dir: true });
            expect(spy).toHaveBeenCalledWith('/', { folder: 7 }, { preserveScroll: true });
            spy.mockRestore();
        });
    });

    // I1: purge must confirm before permanently deleting; both purge and
    // restore should give feedback and clear a stale selected-detail ghost.
    describe('trash restore/purge feedback', () => {
        function trashItem() {
            return { id: 3, name: 'x.md', type: 'md', size: 10, created_at: '2026-01-01' };
        }

        it('Delete forever asks for confirmation before calling router.delete', async () => {
            const delSpy = vi.spyOn(router, 'delete').mockImplementation(() => {});
            const wrapper = mount(FilesIndex, {
                props: { ...base, section: 'trash', files: [trashItem()] },
            });
            wrapper.vm.selectContentItem(trashItem());
            await wrapper.vm.$nextTick();

            const purgeBtn = wrapper.get('[data-pane="detail"]').findAll('button')
                .find((b) => b.text().includes('Delete forever'));
            await purgeBtn!.trigger('click');
            await flushPromises();

            const host = useDialogHost();
            expect(host.state.open).toBe(true);
            expect(delSpy).not.toHaveBeenCalled();

            host.accept();
            await flushPromises();

            expect(delSpy).toHaveBeenCalledWith('/trash/3', expect.anything());
            delSpy.mockRestore();
        });

        it('cancelling the confirmation does not delete', async () => {
            const delSpy = vi.spyOn(router, 'delete').mockImplementation(() => {});
            const wrapper = mount(FilesIndex, {
                props: { ...base, section: 'trash', files: [trashItem()] },
            });
            wrapper.vm.selectContentItem(trashItem());
            await wrapper.vm.$nextTick();

            const purgeBtn = wrapper.get('[data-pane="detail"]').findAll('button')
                .find((b) => b.text().includes('Delete forever'));
            await purgeBtn!.trigger('click');
            await flushPromises();

            const host = useDialogHost();
            host.cancel();
            await flushPromises();

            expect(delSpy).not.toHaveBeenCalled();
            delSpy.mockRestore();
        });

        it('Restore clears a stale selected-detail ghost on success', async () => {
            const postSpy = vi.spyOn(router, 'post').mockImplementation((_url, _data, opts) => {
                opts?.onSuccess?.();
            });
            const wrapper = mount(FilesIndex, {
                props: { ...base, section: 'trash', files: [trashItem()] },
            });
            wrapper.vm.selectContentItem(trashItem());
            await wrapper.vm.$nextTick();

            const restoreBtn = wrapper.get('[data-pane="detail"]').findAll('button')
                .find((b) => b.text().includes('Restore'));
            await restoreBtn!.trigger('click');
            await wrapper.vm.$nextTick();

            expect(postSpy).toHaveBeenCalledWith('/trash/3/restore', {}, expect.anything());
            expect(wrapper.get('[data-pane="detail"]').text()).not.toContain('x.md');
            postSpy.mockRestore();
        });
    });

    // I2: owner-only row actions (star + menu) must not render for sections the
    // viewer doesn't own; owned sections keep full functionality.
    describe('row actions are gated by section', () => {
        const file = { id: 3, name: 'x.md', type: 'md', size: 10, created_at: '2026-01-01' };

        it('hides the row action menu for trash rows', () => {
            const wrapper = mount(FilesIndex, { props: { ...base, section: 'trash', files: [file] } });
            expect(wrapper.findComponent({ name: 'ItemActions' }).exists()).toBe(false);
        });

        it('hides the row action menu for shared rows', () => {
            const wrapper = mount(FilesIndex, { props: { ...base, section: 'shared', files: [file] } });
            expect(wrapper.findComponent({ name: 'ItemActions' }).exists()).toBe(false);
        });

        it('shows the row action menu for the "all" section', () => {
            const wrapper = mount(FilesIndex, { props: { ...base, section: 'all', files: [file] } });
            expect(wrapper.findComponent({ name: 'ItemActions' }).exists()).toBe(true);
        });
    });
});
