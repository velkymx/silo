import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import ContextMenu from '@/Components/ContextMenu.vue';

const items = [
    { text: 'Download', icon: 'download', action: 'download' },
    { divider: true },
    { text: 'Delete', icon: 'trash', action: 'delete' },
];

describe('ContextMenu', () => {
    it('renders items when open and emits the chosen action', async () => {
        const wrapper = mount(ContextMenu, {
            props: { modelValue: true, x: 10, y: 10, items },
            attachTo: document.body,
        });
        const buttons = document.body.querySelectorAll('.ctx-menu .dropdown-item');
        expect(buttons.length).toBe(2); // divider is not a button

        (buttons[1] as HTMLElement).click();
        await wrapper.vm.$nextTick();
        expect(wrapper.emitted('select')![0][0]).toMatchObject({ action: 'delete' });
        // selecting closes the menu
        expect(wrapper.emitted('update:modelValue')!.at(-1)).toEqual([false]);
        wrapper.unmount();
    });

    it('renders nothing when closed', () => {
        mount(ContextMenu, { props: { modelValue: false, x: 0, y: 0, items }, attachTo: document.body });
        expect(document.body.querySelector('.ctx-menu')).toBeNull();
    });
});
