import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises, VueWrapper } from '@vue/test-utils';

const h = vi.hoisted(() => ({
    get: vi.fn(() => Promise.resolve({ person: { id: 2, name: 'Alice', title: 'Engineer', email: 'a@x.co' } })),
    routerGet: vi.fn(),
}));
vi.mock('@/lib/http', () => ({ http: { get: h.get }, getText: vi.fn() }));
vi.mock('@inertiajs/vue3', () => ({
    router: { get: h.routerGet, visit: vi.fn(), on: vi.fn(() => vi.fn()) },
    usePage: () => ({ url: '/directory', props: { auth: { user: { id: 1, name: 'QA' } }, storage: { used: 0, quota: 0 } } }),
    Link: { name: 'Link', template: '<a><slot /></a>' },
}));

import DirectoryIndex from '@/Pages/Directory/Index.vue';

const people = [
    { id: 1, name: 'Bob Sales', title: 'Rep', department: 'Sales', phone: null, avatar_url: null },
    { id: 2, name: 'Alice Eng', title: 'Engineer', department: 'Engineering', phone: null, avatar_url: null },
];

function clickRow(wrapper: VueWrapper, id: number) {
    const table = wrapper.findComponent({ name: 'VibeDataTable' });
    const row = (table.props('items') as Array<{ id: number }>).find((p) => p.id === id);
    table.vm.$emit('row-clicked', row, 0);
}

beforeEach(() => {
    h.get.mockClear();
    h.routerGet.mockClear();
});

describe('Directory/Index (explorer shell)', () => {
    it('lists people in the table with departments in the folder pane', () => {
        const wrapper = mount(DirectoryIndex, { props: { people, departments: ['Engineering', 'Sales'] } });
        expect(wrapper.findComponent({ name: 'VibeDataTable' }).exists()).toBe(true);
        expect(wrapper.text()).toContain('Bob Sales');
        expect(wrapper.text()).toContain('Alice Eng');
        // The tree roots at the section itself (id 0 = "Directory").
        expect(wrapper.find('[data-folder="0"]').text()).toContain('Directory');
        expect(wrapper.text()).toContain('Engineering');
        expect(wrapper.text()).toContain('Sales');
    });

    it('filters the table by department folder', async () => {
        const wrapper = mount(DirectoryIndex, { props: { people, departments: ['Engineering', 'Sales'] } });
        const sales = wrapper.findAll('[data-folder]').find((b) => b.text().includes('Sales'));
        await sales!.trigger('click');
        const table = wrapper.findComponent({ name: 'VibeDataTable' });
        const names = (table.props('items') as Array<{ name: string }>).map((p) => p.name);
        expect(names).toEqual(['Bob Sales']);
    });

    it('collapses the profile pane until a row is selected, then fetches the show endpoint', async () => {
        const wrapper = mount(DirectoryIndex, { props: { people, departments: [] } });
        expect(wrapper.find('[data-pane="detail"]').exists()).toBe(false);
        clickRow(wrapper, 2);
        await flushPromises();
        expect(h.get).toHaveBeenCalledWith('/directory/2/card');
        expect(wrapper.get('[data-pane="detail"]').text()).toContain('Alice');
    });

    it('stale profile response is dropped when a newer click fires first', async () => {
        // First click: slow fetch for person 2 (Alice) — use a unique email as sentinel.
        let resolveFirst!: (v: unknown) => void;
        const first = new Promise<any>((res) => { resolveFirst = res; });
        // Second click: fast fetch for person 1 (Bob).
        h.get
            .mockReturnValueOnce(first)
            .mockResolvedValueOnce({ person: { id: 1, name: 'Bob Sales', title: 'Sales', email: 'bob@current.test' } });

        const wrapper = mount(DirectoryIndex, { props: { people, departments: [] } });
        clickRow(wrapper, 2); // slow
        clickRow(wrapper, 1); // fast, before the first resolves
        await flushPromises();

        // Now resolve the stale Alice response with a unique sentinel.
        resolveFirst({ person: { id: 2, name: 'Alice Eng', email: 'alice@stale.test' } });
        await flushPromises();

        // Profile must NOT contain Alice's stale email; Bob's email must be shown.
        expect(wrapper.text()).not.toContain('alice@stale.test');
        expect(wrapper.text()).toContain('bob@current.test');
    });
});
