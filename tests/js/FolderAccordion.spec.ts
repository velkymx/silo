import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import FolderAccordion from '@/Components/FolderAccordion.vue';

const folders = [
    { id: 1, name: 'Work', parent_id: null },
    { id: 2, name: 'Projects', parent_id: 1 },
    { id: 3, name: 'Archive', parent_id: null },
];

function mountTree(extra = {}) {
    return mount(FolderAccordion, {
        props: { folders, selectedId: null, openIds: new Set<number>(), ...extra },
    });
}

describe('FolderAccordion', () => {
    it('renders top-level folders', () => {
        const wrapper = mountTree();
        expect(wrapper.get('[data-folder="1"]').text()).toContain('Work');
        expect(wrapper.get('[data-folder="3"]').text()).toContain('Archive');
    });

    it('renders nested child folders (recursion)', () => {
        const wrapper = mountTree();
        // Projects (parent_id 1) is only reachable by recursing into Work.
        expect(wrapper.find('[data-folder="2"]').exists()).toBe(true);
        expect(wrapper.get('[data-folder="2"]').text()).toContain('Projects');
    });

    it('emits select-folder with the id when a folder row is clicked', async () => {
        const wrapper = mountTree();
        await wrapper.get('[data-folder="2"]').trigger('click');
        // Deepest emit wins; the top-level accordion re-emits the child id.
        const events = wrapper.emitted('select-folder');
        expect(events).toBeTruthy();
        expect(events!.at(-1)).toEqual([2]);
    });

    it('marks the selected folder active', () => {
        const wrapper = mountTree({ selectedId: 3 });
        expect(wrapper.get('[data-folder="3"]').classes()).toContain('active');
        expect(wrapper.get('[data-folder="1"]').classes()).not.toContain('active');
    });

    it('emits new-folder from the header add button', async () => {
        const wrapper = mountTree();
        await wrapper.get('[data-testid="fa-new"]').trigger('click');
        expect(wrapper.emitted('new-folder')).toHaveLength(1);
    });

    // M4: folder rows must be operable from the keyboard, not just the mouse.
    it('is keyboard-accessible: Enter on a folder row emits select-folder', async () => {
        const wrapper = mountTree();
        await wrapper.get('[data-folder="1"]').trigger('keydown.enter');
        const events = wrapper.emitted('select-folder');
        expect(events).toBeTruthy();
        expect(events!.at(-1)).toEqual([1]);
    });

    it('is keyboard-accessible: Space on a folder row emits select-folder', async () => {
        const wrapper = mountTree();
        await wrapper.get('[data-folder="3"]').trigger('keydown.space');
        const events = wrapper.emitted('select-folder');
        expect(events).toBeTruthy();
        expect(events!.at(-1)).toEqual([3]);
    });

    it('folder rows are focusable (tabindex 0)', () => {
        const wrapper = mountTree();
        expect(wrapper.get('[data-folder="1"]').attributes('tabindex')).toBe('0');
    });
});
