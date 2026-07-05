import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';

const { routerPost } = vi.hoisted(() => ({ routerPost: vi.fn() }));
vi.mock('@inertiajs/vue3', () => ({
    router: { post: routerPost, get: vi.fn(), put: vi.fn(), delete: vi.fn(), visit: vi.fn(), reload: vi.fn(), on: vi.fn(() => () => {}) },
    usePage: () => ({ url: '/', props: { auth: { user: { name: 'T', is_admin: true } }, flash: {}, storage: null, folders: [], savedSearches: [], currentFolder: null } }),
    useForm: (data: Record<string, unknown>) => ({ ...data, processing: false, errors: {}, post: vi.fn(), put: vi.fn(), delete: vi.fn(), reset: vi.fn() }),
    Link: { name: 'Link', template: '<a><slot /></a>' },
    Head: { name: 'Head', template: '<span><slot /></span>' },
}));

import Backups from '@/Pages/Admin/Backups.vue';

const props = {
    backups: [
        { id: 1, filename: 'b1.zip', size: 2048, status: 'ready', compression: 'bzip2', note: null, created_by: 'T', created_at: '2026-06-14 10:00' },
        { id: 2, filename: 'b2.zip', size: 0, status: 'pending', compression: null, note: null, created_by: 'T', created_at: '2026-06-14 10:05' },
    ],
    schedule: { frequency: 'weekly', retention: 7 },
    frequencies: ['off', 'daily', 'weekly', 'monthly'],
};

describe('Admin/Backups', () => {
    beforeEach(() => routerPost.mockClear());

    it('lists backups with status + ultra label', () => {
        const wrapper = mount(Backups, { props });
        expect(wrapper.text()).toContain('b1.zip');
        expect(wrapper.text()).toContain('Ultra');
        expect(wrapper.text()).toContain('pending');
    });

    it('"Back up now" posts to /backups', async () => {
        const wrapper = mount(Backups, { props });
        const btn = wrapper.findAll('button').find((b) => b.text().includes('Back up now'));
        await btn!.trigger('click');
        expect(routerPost).toHaveBeenCalledWith('/backups', {}, expect.anything());
    });
});
