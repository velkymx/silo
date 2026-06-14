import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';

const { routerGet, httpGet } = vi.hoisted(() => ({ routerGet: vi.fn(), httpGet: vi.fn() }));
vi.mock('@inertiajs/vue3', () => ({ router: { get: routerGet } }));
vi.mock('@/lib/http', () => ({ http: { get: httpGet } }));

import FolderTree from '@/Components/FolderTree.vue';

type Node = { id: number; name: string; parent_id: number | null; is_dir: boolean; type: string };

function fakeTree(byUrl: Record<string, Node[]>) {
    httpGet.mockImplementation((url: string) => Promise.resolve(byUrl[url] ?? []));
}

describe('FolderTree (VSCode-style sidebar tree)', () => {
    beforeEach(() => {
        routerGet.mockClear();
    });

    it('loads the root level and renders folders + files', async () => {
        fakeTree({
            '/tree': [
                { id: 1, name: 'Docs', parent_id: null, is_dir: true, type: '' },
                { id: 2, name: 'note.txt', parent_id: null, is_dir: false, type: 'txt' },
            ],
        });

        const wrapper = mount(FolderTree, { props: { folder: null } as any });
        await flushPromises();

        expect(httpGet).toHaveBeenCalledWith('/tree');
        expect(wrapper.text()).toContain('Docs');
        expect(wrapper.text()).toContain('note.txt');
    });

    it('expanding a folder lazily fetches its children', async () => {
        fakeTree({
            '/tree': [{ id: 1, name: 'Docs', parent_id: null, is_dir: true, type: '' }],
            '/tree/1': [{ id: 3, name: 'inside.pdf', parent_id: 1, is_dir: false, type: 'pdf' }],
        });

        const wrapper = mount(FolderTree, { props: { folder: null } as any });
        await flushPromises();
        expect(wrapper.text()).not.toContain('inside.pdf');

        await wrapper.find('.vs-twisty').trigger('click');
        await flushPromises();

        expect(httpGet).toHaveBeenCalledWith('/tree/1');
        expect(wrapper.text()).toContain('inside.pdf');
    });

    it('clicking a file navigates with ?open=id', async () => {
        fakeTree({
            '/tree': [{ id: 5, name: 'pic.png', parent_id: null, is_dir: false, type: 'png' }],
        });

        const wrapper = mount(FolderTree, { props: { folder: null } as any });
        await flushPromises();

        await wrapper.find('.vs-row.file').trigger('click');
        expect(routerGet).toHaveBeenCalledWith('/', { open: 5 }, expect.anything());
    });
});
