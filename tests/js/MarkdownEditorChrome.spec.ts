import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';

vi.mock('@velkymx/vibeui', () => ({ useColorMode: () => ({ colorMode: { value: 'light' } }) }));

import MarkdownEditor from '@/Components/MarkdownEditor.vue';

async function mountEditor(props: Record<string, unknown> = {}) {
    const wrapper = mount(MarkdownEditor, {
        props: { modelValue: '# hi', ...props },
        attachTo: document.body,
    });
    await new Promise((r) => setTimeout(r, 30));
    return wrapper;
}

describe('MarkdownEditor chrome', () => {
    it('renders no tab strips: no bottom mode switch, no visible Write/Preview tabs', async () => {
        const wrapper = await mountEditor({ enableLinks: true });
        expect(wrapper.element.querySelector('.toastui-editor-mode-switch')).toBeNull();
        // Toast UI keeps the Write/Preview container in the DOM but hides it
        // when previewStyle is 'vertical'.
        const tabs = wrapper.element.querySelector('.toastui-editor-md-tab-container') as HTMLElement | null;
        expect(tabs?.style.display).toBe('none');
        wrapper.unmount();
    });

    it('defaults to rich text; the markdown toggle opens source mode pressed', async () => {
        const wrapper = await mountEditor({ enableLinks: true });
        const toggle = wrapper.element.querySelector('.md-mode-toggle') as HTMLButtonElement;
        expect(toggle).not.toBeNull();
        expect(toggle.querySelector('i.bi-markdown')).not.toBeNull();

        // Rich text by default, even for notes.
        const main = wrapper.element.querySelector('.toastui-editor-main') as HTMLElement;
        expect(main.classList.contains('toastui-editor-ww-mode')).toBe(true);
        expect(toggle.classList.contains('active')).toBe(false);

        toggle.click();
        await new Promise((r) => setTimeout(r, 10));
        expect(main.classList.contains('toastui-editor-md-mode')).toBe(true);
        expect(toggle.classList.contains('active')).toBe(true);
        expect(toggle.getAttribute('aria-pressed')).toBe('true');

        toggle.click();
        await new Promise((r) => setTimeout(r, 10));
        expect(main.classList.contains('toastui-editor-ww-mode')).toBe(true);
        expect(toggle.classList.contains('active')).toBe(false);
        wrapper.unmount();
    });
});
