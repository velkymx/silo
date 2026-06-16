import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';

const h = vi.hoisted(() => ({
    viewerCtor: vi.fn(),
    editorCtor: vi.fn(),
    setMarkdown: vi.fn(),
    renderAsync: vi.fn(() => Promise.resolve()),
    xlsxRead: vi.fn(() => ({ SheetNames: ['S1'], Sheets: { S1: {} } })),
    sheetToHtml: vi.fn(() => '<table><tr><td>1</td></tr></table>'),
    superDocCtor: vi.fn(),
    getText: vi.fn(() => Promise.resolve('# Title')),
    getArrayBuffer: vi.fn(() => Promise.resolve(new ArrayBuffer(8))),
    editor: null as null | { emitChange: (md: string) => void },
    colorMode: null as null | { value: string },
}));

vi.mock('@/lib/http', () => ({ getText: h.getText, getArrayBuffer: h.getArrayBuffer }));
vi.mock('@toast-ui/editor/viewer', () => ({
    default: class { constructor(o: unknown) { h.viewerCtor(o); } destroy() {} },
}));
vi.mock('@toast-ui/editor', () => ({
    default: class {
        _md: string;
        _change: (() => void) | null = null;
        constructor(o: { initialValue: string }) { h.editorCtor(o); this._md = o.initialValue; h.editor = this; }
        on(evt: string, cb: () => void) { if (evt === 'change') this._change = cb; }
        getMarkdown() { return this._md; }
        setMarkdown(v: string) { h.setMarkdown(v); this._md = v; }
        destroy() {}
        // simulate a toolbar/user edit producing new markdown (Toast UI normalizes,
        // so the value the editor reports differs from what a parent stored)
        emitChange(md: string) { this._md = md; this._change?.(); }
    },
}));
vi.mock('@toast-ui/editor/dist/toastui-editor-viewer.css', () => ({}));
vi.mock('@toast-ui/editor/dist/theme/toastui-editor-dark.css', () => ({}));
vi.mock('@toast-ui/editor/dist/toastui-editor.css', () => ({}));
vi.mock('docx-preview', () => ({ renderAsync: h.renderAsync }));
vi.mock('xlsx', () => ({ read: h.xlsxRead, utils: { sheet_to_html: h.sheetToHtml } }));
vi.mock('@harbour-enterprises/superdoc', () => ({
    SuperDoc: class { constructor(c: { onReady?: () => void }) { h.superDocCtor(c); c.onReady?.(); } },
}));
vi.mock('@harbour-enterprises/superdoc/style.css', () => ({}));
vi.mock('@velkymx/vibeui', async () => {
    const { ref } = await import('vue');
    const cm = ref('light');
    h.colorMode = cm;
    return { useColorMode: () => ({ colorMode: cm }) };
});

import MarkdownViewer from '@/Components/MarkdownViewer.vue';
import MarkdownEditor from '@/Components/MarkdownEditor.vue';
import DocViewer from '@/Components/DocViewer.vue';
import DocxEditor from '@/Components/DocxEditor.vue';

beforeEach(() => {
    Object.values(h).forEach((f) => (f as { mockClear?: () => void })?.mockClear?.());
    if (h.colorMode) h.colorMode.value = 'light';
});

describe('MarkdownViewer', () => {
    it('renders the fetched markdown via Toast UI Viewer', async () => {
        mount(MarkdownViewer, { props: { url: '/raw/1' } });
        await flushPromises();
        expect(h.getText).toHaveBeenCalledWith('/raw/1');
        expect(h.viewerCtor).toHaveBeenCalled();
    });

    it('shows an error when the fetch fails', async () => {
        h.getText.mockRejectedValueOnce(new Error('nope'));
        const wrapper = mount(MarkdownViewer, { props: { url: '/raw/1' } });
        await flushPromises();
        expect(wrapper.text()).toContain('Could not render this file.');
    });
});

describe('MarkdownEditor', () => {
    it('builds the editor with the initial value', () => {
        mount(MarkdownEditor, { props: { modelValue: 'hello' } });
        expect(h.editorCtor).toHaveBeenCalledWith(expect.objectContaining({ initialValue: 'hello' }));
    });

    it('pushes external modelValue changes into the editor', async () => {
        const wrapper = mount(MarkdownEditor, { props: { modelValue: 'a' } });
        await wrapper.setProps({ modelValue: 'b' });
        expect(h.setMarkdown).toHaveBeenCalledWith('b');
    });

    it('does not feed the editor its own output back (no toolbar revert)', async () => {
        // v-model: the editor's change emit flows back into modelValue.
        const wrapper = mount(MarkdownEditor, {
            props: { modelValue: 'hi', 'onUpdate:modelValue': (v: string) => wrapper.setProps({ modelValue: v }) },
        });
        h.setMarkdown.mockClear();
        // A toolbar action emits new markdown; it round-trips through v-model.
        h.editor!.emitChange('hi **bold**');
        await flushPromises();
        expect(wrapper.props('modelValue')).toBe('hi **bold**');
        // The editor's own emit must NOT trigger setMarkdown (which would revert it).
        expect(h.setMarkdown).not.toHaveBeenCalled();
    });

    it('toggles theme in place — no destroy/rebuild on color-mode flip', async () => {
        mount(MarkdownEditor, { props: { modelValue: 'x' } });
        expect(h.editorCtor).toHaveBeenCalledTimes(1);
        h.colorMode!.value = 'dark';
        await flushPromises();
        // Old behavior rebuilt the editor (a 2nd construction); the fix toggles a class.
        expect(h.editorCtor).toHaveBeenCalledTimes(1);
    });
});

describe('DocViewer', () => {
    it('renders a docx via docx-preview', async () => {
        mount(DocViewer, { props: { url: '/raw/2', type: 'docx' } });
        await flushPromises();
        expect(h.renderAsync).toHaveBeenCalled();
    });

    it('renders a spreadsheet via SheetJS', async () => {
        mount(DocViewer, { props: { url: '/raw/3', type: 'xlsx' } });
        await flushPromises();
        expect(h.xlsxRead).toHaveBeenCalled();
        expect(h.sheetToHtml).toHaveBeenCalled();
    });

    it('shows an unsupported message for other types', async () => {
        const wrapper = mount(DocViewer, { props: { url: '/raw/4', type: 'png' } });
        await flushPromises();
        expect(wrapper.text()).toContain('No inline preview for this file type.');
    });

    it('shows an error when rendering throws', async () => {
        h.getArrayBuffer.mockRejectedValueOnce(new Error('boom'));
        const wrapper = mount(DocViewer, { props: { url: '/raw/5', type: 'docx' } });
        await flushPromises();
        expect(wrapper.text()).toContain('Could not render this document.');
    });
});

describe('DocxEditor', () => {
    it('loads SuperDoc and emits ready', async () => {
        const wrapper = mount(DocxEditor, { props: { url: '/raw/6', name: 'memo.docx' } });
        await flushPromises();
        expect(h.superDocCtor).toHaveBeenCalled();
        expect(wrapper.emitted('ready')).toBeTruthy();
    });
});
