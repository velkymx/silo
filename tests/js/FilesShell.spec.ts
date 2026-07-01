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
});
