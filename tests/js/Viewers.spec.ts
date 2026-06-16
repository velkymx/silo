import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { ref } from 'vue';

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
}));

vi.mock('@/lib/http', () => ({ getText: h.getText, getArrayBuffer: h.getArrayBuffer }));
vi.mock('@toast-ui/editor/viewer', () => ({
    default: class { constructor(o: unknown) { h.viewerCtor(o); } destroy() {} },
}));
vi.mock('@toast-ui/editor', () => ({
    default: class {
        _md: string;
        constructor(o: { initialValue: string }) { h.editorCtor(o); this._md = o.initialValue; }
        on() {} getMarkdown() { return this._md; } setMarkdown(v: string) { h.setMarkdown(v); this._md = v; } destroy() {}
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
vi.mock('@velkymx/vibeui', () => ({ useColorMode: () => ({ colorMode: ref('light') }) }));

import MarkdownViewer from '@/Components/MarkdownViewer.vue';
import MarkdownEditor from '@/Components/MarkdownEditor.vue';
import DocViewer from '@/Components/DocViewer.vue';
import DocxEditor from '@/Components/DocxEditor.vue';

beforeEach(() => Object.values(h).forEach((f) => f.mockClear?.()));

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
