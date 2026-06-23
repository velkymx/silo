import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';

const h = vi.hoisted(() => ({
    get: vi.fn(() => Promise.resolve({ person: { id: 2, name: 'Alice', title: 'Engineer', email: 'a@x.co' } })),
}));
vi.mock('@/lib/http', () => ({ http: { get: h.get }, getText: vi.fn() }));

import DirectoryIndex from '@/Pages/Directory/Index.vue';

const people = [
    { id: 1, name: 'Bob Sales', title: 'Rep', department: 'Sales', phone: null, avatar_url: null },
    { id: 2, name: 'Alice Eng', title: 'Engineer', department: 'Engineering', phone: null, avatar_url: null },
];

beforeEach(() => h.get.mockClear());

describe('Directory/Index', () => {
    it('renders people grouped by department', () => {
        const wrapper = mount(DirectoryIndex, { props: { people, departments: ['Engineering', 'Sales'] } });
        expect(wrapper.text()).toContain('Bob Sales');
        expect(wrapper.text()).toContain('Alice Eng');
        expect(wrapper.text()).toContain('Engineering');
        expect(wrapper.text()).toContain('Sales');
    });

    it('filters people by the search box', async () => {
        const wrapper = mount(DirectoryIndex, { props: { people, departments: [] } });
        await wrapper.get('input[type="search"]').setValue('alice');
        expect(wrapper.text()).toContain('Alice Eng');
        expect(wrapper.text()).not.toContain('Bob Sales');
    });

    it('opens a profile by fetching the show endpoint', async () => {
        const wrapper = mount(DirectoryIndex, { props: { people, departments: [] } });
        // Departments sort alphabetically → Engineering (Alice, id 2) renders first.
        await wrapper.findAll('.person-card')[0].trigger('click');
        await flushPromises();
        expect(h.get).toHaveBeenCalledWith('/directory/2');
    });
});
