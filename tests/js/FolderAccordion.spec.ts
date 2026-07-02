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

    it('renders nested child folders when the parent is expanded (recursion)', () => {
        // Work (id 1) is an ancestor via openIds, so its subtree is expanded.
        const wrapper = mountTree({ openIds: new Set<number>([1]) });
        expect(wrapper.find('[data-folder="2"]').exists()).toBe(true);
        expect(wrapper.get('[data-folder="2"]').text()).toContain('Projects');
    });

    it('collapses child folders until the parent is expanded', () => {
        const wrapper = mountTree();
        // Nothing expanded → Projects (child of Work) is not rendered.
        expect(wrapper.find('[data-folder="2"]').exists()).toBe(false);
    });

    it('expands a branch when its chevron is clicked', async () => {
        const wrapper = mountTree();
        await wrapper.get('[data-folder="1"] .fa-chevron').trigger('click');
        expect(wrapper.find('[data-folder="2"]').exists()).toBe(true);
    });

    it('emits select-folder with the id when a nested folder row is clicked', async () => {
        const wrapper = mountTree({ openIds: new Set<number>([1]) });
        await wrapper.get('[data-folder="2"]').trigger('click');
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
