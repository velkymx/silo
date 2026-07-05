import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';

const { routerGet } = vi.hoisted(() => ({ routerGet: vi.fn() }));
vi.mock('@inertiajs/vue3', () => ({
    router: { get: routerGet, post: vi.fn(), visit: vi.fn(), on: vi.fn(() => () => {}) },
    usePage: () => ({ url: '/', props: { auth: { user: { id: 1, name: 'QA' } }, flash: {}, errors: {}, storage: { used: 0, quota: 0 } } }),
    Link: { name: 'Link', template: '<a><slot /></a>' },
}));

import SharedFolder from '@/Pages/Shared/Folder.vue';

const files = [{ id: 21, name: 'photo.png', owner: 'Alice', size: 2048, type: 'png', abilities: ['view'], url: '/raw/21' }];

describe('Shared pages', () => {
    beforeEach(() => routerGet.mockClear());

    it('Folder breadcrumb navigates to an ancestor', async () => {
        const wrapper = mount(SharedFolder, {
            props: { current: { id: 5, name: 'Day1' }, trail: [{ id: 2, name: 'Trip' }], folders: [], files },
        });
        // Breadcrumb stub is a passthrough; trigger the handler directly.
        wrapper.findComponent({ name: 'VibeBreadcrumb' }).vm.$emit('item-click', { item: { folder: 2 } });
        expect(routerGet).toHaveBeenCalledWith('/shared/2', {}, expect.anything());
    });

    it('Folder breadcrumb to root goes to /shared', async () => {
        const wrapper = mount(SharedFolder, {
            props: { current: { id: 5, name: 'Day1' }, trail: [], folders: [], files },
        });
        wrapper.findComponent({ name: 'VibeBreadcrumb' }).vm.$emit('item-click', { item: { folder: null } });
        expect(routerGet).toHaveBeenCalledWith('/shared', {}, expect.anything());
    });
});
