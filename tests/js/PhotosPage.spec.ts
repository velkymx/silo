import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { reactive } from 'vue';

const s = vi.hoisted(() => ({
    routerGet: vi.fn(), routerPost: vi.fn(), routerDelete: vi.fn(), formPost: vi.fn(), formReset: vi.fn(),
}));
vi.mock('@inertiajs/vue3', () => ({
    router: { get: s.routerGet, post: s.routerPost, delete: s.routerDelete, on: vi.fn(() => () => {}) },
    // reactive() so disabled-gated submit buttons re-enable when fields change.
    useForm: (data: Record<string, unknown>) => reactive({ ...data, processing: false, errors: {}, post: s.formPost, reset: s.formReset }),
    usePage: () => ({ props: { flash: {}, errors: {} } }),
}));
vi.mock('vue-advanced-cropper', () => ({ Cropper: { name: 'Cropper', template: '<div class="cropper-stub" />' } }));
vi.mock('vue-advanced-cropper/dist/style.css', () => ({}));

import Photos from '@/Pages/Photos/Index.vue';
import { useDialogHost } from '@/composables/useConfirm';

const photos = [
    { id: 1, name: 'a.jpg', url: '/r/1', thumb_url: '/t/1', date: '2026-06', date_label: 'June 2026', starred: false },
    { id: 2, name: 'b.jpg', url: '/r/2', thumb_url: '/t/2', date: '2026-06', date_label: 'June 2026', starred: true },
];

beforeEach(() => Object.values(s).forEach((f) => f.mockClear()));

describe('Photos/Index page', () => {
    it('renders the month timeline group', () => {
        const wrapper = mount(Photos, { props: { photos, albums: [], tags: [] } });
        expect(wrapper.text()).toContain('June 2026');
        expect(wrapper.findAll('img.film-thumb, .photo-thumb img').length).toBeGreaterThan(0);
    });

    it('shows an empty state with no photos', () => {
        const wrapper = mount(Photos, { props: { photos: [], albums: [], tags: [] } });
        expect(wrapper.text()).toContain('No photos yet');
    });

    it('changing the album filter navigates to /photos', async () => {
        const wrapper = mount(Photos, { props: { photos, albums: [{ id: 8, name: 'Trip', count: 2 }], tags: [] } });
        const select = wrapper.find('[data-stub="VibeFormSelect"]');
        await select.setValue('8');
        expect(s.routerGet).toHaveBeenCalledWith('/photos', expect.objectContaining({ album: '8' }), expect.anything());
    });

    it('starring a photo posts to the star route', async () => {
        const wrapper = mount(Photos, { props: { photos, albums: [], tags: [] } });
        const starBtn = wrapper.findAll('button').find((b) => b.attributes('aria-label')?.startsWith('Star a.jpg'));
        await starBtn!.trigger('click');
        expect(s.routerPost).toHaveBeenCalledWith('/files/1/star', {}, expect.anything());
    });

    it('creating an album posts to /photos/albums', async () => {
        const wrapper = mount(Photos, { props: { photos, albums: [], tags: [] } });
        // The album-name field is the only text input; filling it enables Create.
        await wrapper.find('input[data-stub="VibeFormInput"]').setValue('Summer 2026');
        const create = wrapper.findAll('button').find((b) => b.text().trim() === 'Create');
        await create!.trigger('click');
        expect(s.formPost).toHaveBeenCalledWith('/photos/albums', expect.anything());
    });

    it('batch delete confirms then posts the ids', async () => {
        const wrapper = mount(Photos, { props: { photos, albums: [], tags: [] } });
        // Enter select mode, select the first photo.
        const selectToggle = wrapper.findAll('button').find((b) => b.text().includes('Select'));
        await selectToggle!.trigger('click');
        await wrapper.find('.photo-cell').trigger('click');
        const del = wrapper.findAll('button').find((b) => b.text().includes('Delete'));
        await del!.trigger('click');
        const host = useDialogHost();
        expect(host.state.open).toBe(true);
        host.accept();
        await flushPromises();
        expect(s.routerPost).toHaveBeenCalledWith('/files/batch/delete', { ids: [1] }, expect.anything());
    });
});
