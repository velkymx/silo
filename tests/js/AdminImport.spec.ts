import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';

const { routerPost } = vi.hoisted(() => ({ routerPost: vi.fn() }));
vi.mock('@inertiajs/vue3', () => ({
    router: { post: routerPost, get: vi.fn(), put: vi.fn(), delete: vi.fn(), visit: vi.fn(), reload: vi.fn() },
    usePage: () => ({ props: { auth: { user: { name: 'T', is_admin: true } }, flash: {}, storage: null, folders: [], savedSearches: [], currentFolder: null } }),
    useForm: (data: Record<string, unknown>) => ({ ...data, processing: false, errors: {}, progress: null, post: vi.fn(), put: vi.fn(), delete: vi.fn(), reset: vi.fn() }),
    Link: { name: 'Link', template: '<a><slot /></a>' },
    Head: { name: 'Head', template: '<span><slot /></span>' },
}));

import Import from '@/Pages/Admin/Import.vue';

describe('Admin/Import', () => {
    beforeEach(() => routerPost.mockClear());

    it('shows the source path + file count', () => {
        const wrapper = mount(Import, { props: { root: '/import', fileCount: 7 } });
        expect(wrapper.text()).toContain('/import');
        expect(wrapper.text()).toContain('7');
    });

    it('handles an unmounted folder (null count)', () => {
        const wrapper = mount(Import, { props: { root: '/import', fileCount: null as unknown as number } });
        expect(wrapper.text().toLowerCase()).toContain('not mounted');
    });

    it('re-scan posts the folder name', async () => {
        const wrapper = mount(Import, { props: { root: '/import', fileCount: 3 } });
        await wrapper.find('input').setValue('Shared Drive');
        const btn = wrapper.findAll('button').find((b) => b.text().includes('Re-scan'));
        await btn!.trigger('click');
        await flushPromises();
        expect(routerPost).toHaveBeenCalledWith('/import/rescan', { name: 'Shared Drive' }, expect.anything());
    });
});
