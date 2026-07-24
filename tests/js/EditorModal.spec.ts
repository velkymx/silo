import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';

const { routerPost, routerPut, getText } = vi.hoisted(() => ({ routerPost: vi.fn(), routerPut: vi.fn(), getText: vi.fn() }));
vi.mock('@inertiajs/vue3', () => ({ router: { post: routerPost, put: routerPut } }));
vi.mock('@/lib/http', () => ({ getText }));

import EditorModal from '@/Components/EditorModal.vue';

const opts = { global: { stubs: { MarkdownEditor: { template: '<textarea class="md"></textarea>', props: ['modelValue'] } } } };

describe('EditorModal', () => {
    beforeEach(() => { routerPost.mockClear(); routerPut.mockClear(); getText.mockReset(); });

    it('create mode shows a filename field and posts /files/text', async () => {
        const wrapper = mount(EditorModal, { props: { modelValue: false, item: null, creating: true, kind: 'markdown', parentId: 4 }, ...opts });
        await wrapper.setProps({ modelValue: true });
        await flushPromises();
        // Create mode shows the filename input (the modal title isn't in the stub).
        expect(wrapper.find('input').exists()).toBe(true);
        const create = wrapper.findAll('button').find((b) => b.text().includes('Create'));
        await create!.trigger('click');
        expect(routerPost).toHaveBeenCalledWith('/files/text', expect.objectContaining({ name: 'untitled.md', parent_id: 4 }), expect.anything());
        expect(getText).not.toHaveBeenCalled();
    });

    it('edit mode loads content then saves via PUT', async () => {
        getText.mockResolvedValue('# hi');
        const wrapper = mount(EditorModal, { props: { modelValue: false, item: { id: 5, name: 'a.md' }, creating: false, kind: 'markdown', parentId: null }, ...opts });
        await wrapper.setProps({ modelValue: true });
        await flushPromises();
        expect(getText).toHaveBeenCalledWith('/raw/5');
        const save = wrapper.findAll('button').find((b) => b.text().includes('Save'));
        await save!.trigger('click');
        expect(routerPut).toHaveBeenCalledWith('/files/5/content', { content: '# hi' }, expect.anything());
    });

    it('stale load result is dropped when a newer open fires first', async () => {
        // First open: slow fetch for file 5
        let resolveFirst!: (v: string) => void;
        const first = new Promise<string>((res) => { resolveFirst = res; });
        // Second open: fast fetch for file 7
        getText
            .mockReturnValueOnce(first)
            .mockResolvedValueOnce('# file-7');

        // Stub that actually reflects modelValue so we can assert on it.
        const bindingOpts = {
            global: {
                stubs: {
                    MarkdownEditor: {
                        template: '<textarea class="md" :value="modelValue"></textarea>',
                        props: ['modelValue'],
                        emits: ['update:modelValue'],
                    },
                },
            },
        };

        const wrapper = mount(EditorModal, {
            props: { modelValue: false, item: { id: 5, name: 'a.md' }, creating: false, kind: 'markdown', parentId: null },
            ...bindingOpts,
        });

        // Open → triggers fetch for file 5 (stalls).
        await wrapper.setProps({ modelValue: true });
        // Close then immediately re-open with file 7 — triggers a second fetch.
        await wrapper.setProps({ modelValue: false });
        await wrapper.setProps({ item: { id: 7, name: 'b.md' }, modelValue: true });
        await flushPromises();

        // Now resolve the stale file-5 response after file-7 already loaded.
        resolveFirst('# file-5-stale');
        await flushPromises();

        // The textarea must NOT contain the stale file-5 content.
        expect((wrapper.find('textarea.md').element as HTMLTextAreaElement).value).not.toContain('file-5-stale');
    });
});
