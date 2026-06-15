import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import QuickLookModal from '@/Components/QuickLookModal.vue';

const file = { id: 7, name: 'pic.png', type: 'png', mime: 'image/png', url: '/raw/7', size: 2048 };
const menu = [{ text: 'Delete', icon: 'trash', action: 'delete' }];

const stubs = { MarkdownViewer: true, DocViewer: true };

describe('QuickLookModal', () => {
    it('renders the filename + index counter', () => {
        const wrapper = mount(QuickLookModal, {
            props: { modelValue: true, file, index: 0, total: 3, menu },
            global: { stubs },
        });
        expect(wrapper.text()).toContain('pic.png');
        expect(wrapper.text()).toContain('1 / 3');
        expect(wrapper.find('img').attributes('src')).toBe('/raw/7');
    });

    it('emits step on prev/next', async () => {
        const wrapper = mount(QuickLookModal, {
            props: { modelValue: true, file, index: 1, total: 3, menu },
            global: { stubs },
        });
        const btns = wrapper.findAll('button');
        const prev = btns.find((b) => b.attributes('aria-label') === 'Previous file');
        const next = btns.find((b) => b.attributes('aria-label') === 'Next file');
        await prev!.trigger('click');
        await next!.trigger('click');
        expect(wrapper.emitted('step')).toEqual([[-1], [1]]);
    });

    it('emits action from the actions dropdown', async () => {
        const wrapper = mount(QuickLookModal, {
            props: { modelValue: true, file, index: 0, total: 1, menu },
            global: { stubs },
        });
        await wrapper.find('.dd-item').trigger('click');
        expect(wrapper.emitted('action')).toBeTruthy();
    });

    it('closes via the close button', async () => {
        const wrapper = mount(QuickLookModal, {
            props: { modelValue: true, file, index: 0, total: 1, menu },
            global: { stubs },
        });
        const close = wrapper.findAll('button').find((b) => b.attributes('aria-label') === 'Close preview');
        await close!.trigger('click');
        expect(wrapper.emitted('update:modelValue')!.at(-1)).toEqual([false]);
    });
});
