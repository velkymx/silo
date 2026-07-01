import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
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
});
