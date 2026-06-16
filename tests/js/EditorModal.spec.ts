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
});
