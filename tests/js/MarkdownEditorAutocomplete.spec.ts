import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';

const h = vi.hoisted(() => ({
    editorCtor: vi.fn(),
    setSelection: vi.fn(),
    replaceSelection: vi.fn(),
    httpGet: vi.fn(),
    editor: null as null | { type: (md: string, sel: number[][]) => void },
}));

vi.mock('@/lib/http', () => ({
    http: { get: h.httpGet },
    getText: vi.fn(() => Promise.resolve('')),
}));
vi.mock('@toast-ui/editor', () => ({
    default: class {
        _md = '';
        _sel: number[][] = [[0, 0], [0, 0]];
        _change: (() => void) | null = null;
        constructor(o: { initialValue: string }) { h.editorCtor(o); this._md = o.initialValue ?? ''; h.editor = this; }
        on(evt: string, cb: () => void) { if (evt === 'change') this._change = cb; }
        getMarkdown() { return this._md; }
        setMarkdown(v: string) { this._md = v; }
        getSelection() { return this._sel; }
        setSelection(s: number[], e: number[]) { h.setSelection(s, e); }
        replaceSelection(t: string) { h.replaceSelection(t); }
        focus() {}
        destroy() {}
        type(md: string, sel: number[][]) { this._md = md; this._sel = sel; this._change?.(); }
    },
}));
vi.mock('@toast-ui/editor/dist/toastui-editor.css', () => ({}));
vi.mock('@toast-ui/editor/dist/theme/toastui-editor-dark.css', () => ({}));
vi.mock('@velkymx/vibeui', async () => {
    const { ref } = await import('vue');
    return { useColorMode: () => ({ colorMode: ref('light') }) };
});

import MarkdownEditor from '@/Components/MarkdownEditor.vue';

beforeEach(() => {
    vi.useFakeTimers();
    h.editorCtor.mockClear();
    h.setSelection.mockClear();
    h.replaceSelection.mockClear();
    h.httpGet.mockReset();
});
afterEach(() => vi.useRealTimers());

describe('MarkdownEditor autocomplete', () => {
    it('opens the editor in markdown mode when links are enabled', () => {
        mount(MarkdownEditor, { props: { modelValue: '', enableLinks: true } });
        expect(h.editorCtor).toHaveBeenCalledWith(expect.objectContaining({ initialEditType: 'markdown' }));
    });

    it('shows wikilink suggestions and inserts the chosen title', async () => {
        h.httpGet.mockResolvedValue({ results: [{ id: 1, title: 'Roadmap' }] });
        const wrapper = mount(MarkdownEditor, { props: { modelValue: '', enableLinks: true } });

        h.editor!.type('See [[Ro', [[0, 8], [0, 8]]);
        await vi.runAllTimersAsync();
        await flushPromises();

        expect(h.httpGet).toHaveBeenCalledWith('/notes/search/notes?q=Ro');
        expect(wrapper.text()).toContain('Roadmap');

        await wrapper.get('.md-editor-fill').trigger('keydown', { key: 'Enter' });
        // Selects the trigger token ([[Ro = 4 chars back) and inserts the full link.
        expect(h.setSelection).toHaveBeenCalledWith([0, 4], [0, 8]);
        expect(h.replaceSelection).toHaveBeenCalledWith('[[Roadmap]]');
    });

    it('queries users for @ mentions and inserts the handle', async () => {
        h.httpGet.mockResolvedValue({ results: [{ id: 2, name: 'Alice', handle: 'alice' }] });
        const wrapper = mount(MarkdownEditor, { props: { modelValue: '', enableLinks: true } });

        h.editor!.type('ping @al', [[0, 8], [0, 8]]);
        await vi.runAllTimersAsync();
        await flushPromises();

        expect(h.httpGet).toHaveBeenCalledWith('/notes/search/users?q=al');
        await wrapper.get('.md-editor-fill').trigger('keydown', { key: 'Enter' });
        expect(h.replaceSelection).toHaveBeenCalledWith('@alice');
    });

    it('closes the popup on Escape', async () => {
        h.httpGet.mockResolvedValue({ results: [{ id: 1, title: 'Roadmap' }] });
        const wrapper = mount(MarkdownEditor, { props: { modelValue: '', enableLinks: true } });
        h.editor!.type('[[Ro', [[0, 4], [0, 4]]);
        await vi.runAllTimersAsync();
        await flushPromises();
        expect(wrapper.text()).toContain('Roadmap');

        await wrapper.get('.md-editor-fill').trigger('keydown', { key: 'Escape' });
        expect(wrapper.text()).not.toContain('Roadmap');
    });
});
