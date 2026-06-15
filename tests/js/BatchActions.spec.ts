import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';

const { routerPost } = vi.hoisted(() => ({ routerPost: vi.fn() }));
vi.mock('@inertiajs/vue3', () => ({ router: { post: routerPost } }));

import BatchActions from '@/Components/BatchActions.vue';

const items = [{ id: 1, name: 'a.txt' }, { id: 2, name: 'b.txt' }];
const destinationOptions = [{ value: null, text: 'Home' }, { value: 9, text: 'Docs' }];

describe('BatchActions', () => {
    beforeEach(() => routerPost.mockClear());

    it('shows the selected count and hides when empty', () => {
        const empty = mount(BatchActions, { props: { selectedItems: [], currentId: null, destinationOptions } });
        expect(empty.find('.batch-bar').exists()).toBe(false);

        const wrapper = mount(BatchActions, { props: { selectedItems: items, currentId: null, destinationOptions } });
        expect(wrapper.find('.batch-bar').text()).toContain('2 selected');
    });

    it('Move… opens the modal and posts the batch move', async () => {
        const wrapper = mount(BatchActions, { props: { selectedItems: items, currentId: 5, destinationOptions } });
        await wrapper.findAll('button').find((b) => b.text().includes('Move…'))!.trigger('click');
        const move = wrapper.findAll('button').find((b) => b.text().startsWith('Move 2'));
        await move!.trigger('click');
        expect(routerPost).toHaveBeenCalledWith('/files/batch/move', expect.objectContaining({ ids: [1, 2] }), expect.anything());
    });

    it('Clear emits cleared', async () => {
        const wrapper = mount(BatchActions, { props: { selectedItems: items, currentId: null, destinationOptions } });
        await wrapper.findAll('button').find((b) => b.text() === 'Clear')!.trigger('click');
        expect(wrapper.emitted('cleared')).toBeTruthy();
    });

    it('New Folder posts batch/folder with the parent id', async () => {
        const wrapper = mount(BatchActions, { props: { selectedItems: items, currentId: 5, destinationOptions } });
        await wrapper.findAll('button').find((b) => b.text().includes('New Folder'))!.trigger('click');
        const create = wrapper.findAll('button').find((b) => b.text() === 'Create');
        await create!.trigger('click');
        expect(routerPost).toHaveBeenCalledWith('/files/batch/folder', expect.objectContaining({ ids: [1, 2], parent_id: 5 }), expect.anything());
    });
});
