import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import { router } from '@inertiajs/vue3';
import { vi } from 'vitest';
import FilesIndex from '@/Pages/Files/Index.vue';

const base = {
    folders: [], files: [], current: null, breadcrumbs: [], allFolders: [],
    allTags: [], storage: { used: 0, quota: 0 }, filters: { search: '', sort: 'name', direction: 'asc' },
    section: 'all',
};

describe('Files shell', () => {
    it('renders the four-pane shell with the section rail', () => {
        const wrapper = mount(FilesIndex, { props: base });
        expect(wrapper.findComponent({ name: 'FourPane' }).exists()).toBe(true);
        expect(wrapper.findComponent({ name: 'SectionRail' }).exists()).toBe(true);
        expect(wrapper.find('[data-section="all"]').exists()).toBe(true);
        expect(wrapper.find('[data-section="trash"]').exists()).toBe(true);
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
});
