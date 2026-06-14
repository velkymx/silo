import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import ItemActions from '@/Components/ItemActions.vue';

const item = { id: 4, starred: false };
const menu = [{ text: 'Delete', icon: 'trash', action: 'delete' }];

describe('ItemActions', () => {
    it('exposes an accessible "File Actions" label on the menu trigger', () => {
        const wrapper = mount(ItemActions, { props: { item, menu } });
        expect(wrapper.find('.visually-hidden').text()).toBe('File Actions');
    });

    it('emits star with the item', async () => {
        const wrapper = mount(ItemActions, { props: { item, menu } });
        const star = wrapper.findAll('button').find((b) => b.attributes('title')?.includes('Star'));
        await star!.trigger('click');
        expect(wrapper.emitted('star')?.[0]).toEqual([item]);
    });

    it('emits action when a menu item is clicked', async () => {
        const wrapper = mount(ItemActions, { props: { item, menu } });
        await wrapper.find('.dd-item').trigger('click');
        expect(wrapper.emitted('action')).toBeTruthy();
    });
});
