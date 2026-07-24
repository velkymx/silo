import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import FileTree from '@/Components/Files/FileTree.vue';

function base(overrides: Record<string, any> = {}) {
    const childrenCache = new Map<number | null, any>([
        [null, {
            folders: [{ id: 1, name: 'Docs', is_dir: true, starred: false, item_count: 2, has_children: true }],
            files: [{ id: 9, name: 'a.md', is_dir: false, type: 'md', size: 10 }],
        }],
        [1, { folders: [], files: [{ id: 10, name: 'inside.md', is_dir: false, type: 'md', size: 5 }] }],
    ]);
    return {
        nodeId: null, childrenCache, expanded: new Set<number>(), loading: new Set<number>(),
        selectedId: null, selectedSet: new Set<number>(), ...overrides,
    };
}

describe('FileTree', () => {
    it('renders top-level folders and files', () => {
        const w = mount(FileTree, { props: base() });
        expect(w.text()).toContain('Docs');
        expect(w.text()).toContain('a.md');
    });

    it('emits toggle when a folder row is clicked', async () => {
        const w = mount(FileTree, { props: base() });
        await w.get('[data-tree-folder="1"]').trigger('click');
        expect(w.emitted('toggle')?.[0]).toEqual([1]);
    });

    it('shows nested children when the folder is expanded', () => {
        const w = mount(FileTree, { props: base({ expanded: new Set([1]) }) });
        expect(w.text()).toContain('inside.md');
    });

    it('emits select on a file click and open on dblclick', async () => {
        const w = mount(FileTree, { props: base() });
        await w.get('[data-tree-file="9"]').trigger('click');
        expect(w.emitted('select')?.[0][0]).toMatchObject({ id: 9 });
        await w.get('[data-tree-file="9"]').trigger('dblclick');
        expect(w.emitted('open')?.[0][0]).toMatchObject({ id: 9 });
    });
});
