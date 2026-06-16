import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';

const { routerPost, routerDelete } = vi.hoisted(() => ({ routerPost: vi.fn(), routerDelete: vi.fn() }));
vi.mock('@inertiajs/vue3', () => ({
    router: { post: routerPost, delete: routerDelete, on: vi.fn(() => () => {}) },
    usePage: () => ({ props: { flash: {}, errors: {} } }),
}));

import Trash from '@/Pages/Trash/Index.vue';
import { useDialogHost } from '@/composables/useConfirm';

const items = [{ id: 9, name: 'gone.txt', type: 'txt', is_dir: false, deleted_at: 'today' }];

describe('Trash/Index page', () => {
    beforeEach(() => { routerPost.mockClear(); routerDelete.mockClear(); });

    it('Restore posts to the restore route', async () => {
        const wrapper = mount(Trash, { props: { items } });
        const restore = wrapper.findAll('button').find((b) => b.text().includes('Restore'));
        await restore!.trigger('click');
        expect(routerPost).toHaveBeenCalledWith('/trash/9/restore', {}, expect.anything());
    });

    it('Purge confirms then deletes the item', async () => {
        const wrapper = mount(Trash, { props: { items } });
        const purge = wrapper.findAll('button').filter((b) => b.element.querySelector('.bi') && b.text() === '').pop();
        await purge!.trigger('click');
        const host = useDialogHost();
        expect(host.state.open).toBe(true);
        host.accept();
        await flushPromises();
        expect(routerDelete).toHaveBeenCalledWith('/trash/9', expect.anything());
    });

    it('Empty Trash confirms then deletes everything', async () => {
        const wrapper = mount(Trash, { props: { items } });
        const empty = wrapper.findAll('button').find((b) => b.text().includes('Empty Trash'));
        await empty!.trigger('click');
        const host = useDialogHost();
        expect(host.state.open).toBe(true);
        host.accept();
        await flushPromises();
        expect(routerDelete).toHaveBeenCalledWith('/trash/empty', expect.anything());
    });
});
