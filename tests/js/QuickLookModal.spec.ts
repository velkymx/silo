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

    it('shows a hover thumbnail of the next file', async () => {
        const nextFile = { id: 9, name: 'next.png', type: 'png', thumb_url: '/t/9' };
        const wrapper = mount(QuickLookModal, {
            props: { modelValue: true, file, index: 0, total: 2, menu, nextFile },
            global: { stubs },
        });
        // The next button's wrapper carries the hover handlers.
        const nextBtn = wrapper.findAll('button').find((b) => b.attributes('aria-label') === 'Next file')!;
        const wrap = wrapper.findAll('.position-relative').find((w) => w.element.contains(nextBtn.element))!;
        await wrap.trigger('mouseenter');
        const peek = wrap.find('.ql-peek img');
        expect(peek.exists()).toBe(true);
        expect(peek.attributes('src')).toBe('/t/9');
    });

    it('shows fmtBytes-formatted size for non-image files', () => {
        const zipFile = { id: 8, name: 'archive.zip', type: 'zip', mime: 'application/zip', url: '/raw/8', size: 2097152 };
        const wrapper = mount(QuickLookModal, {
            props: { modelValue: true, file: zipFile, index: 0, total: 1, menu },
            global: { stubs },
        });
        // 2097152 bytes = 2.0 MB via fmtBytes; inline formatter would say "2048.0 KB"
        expect(wrapper.text()).toContain('2.0 MB');
        expect(wrapper.text()).not.toContain('2048');
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
