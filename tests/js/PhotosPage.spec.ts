import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { reactive } from 'vue';

const s = vi.hoisted(() => ({
    routerGet: vi.fn(), routerPost: vi.fn(), routerDelete: vi.fn(), formPost: vi.fn(), formReset: vi.fn(),
}));
vi.mock('@inertiajs/vue3', () => ({
    router: { get: s.routerGet, post: s.routerPost, delete: s.routerDelete, visit: vi.fn(), on: vi.fn(() => () => {}) },
    // reactive() so disabled-gated submit buttons re-enable when fields change.
    useForm: (data: Record<string, unknown>) => reactive({ ...data, processing: false, errors: {}, post: s.formPost, reset: s.formReset }),
    usePage: () => ({ url: '/photos', props: { auth: { user: { id: 1, name: 'QA' } }, flash: {}, errors: {}, storage: { used: 0, quota: 0 } } }),
    Link: { name: 'Link', template: '<a><slot /></a>' },
}));
vi.mock('vue-advanced-cropper', () => ({ Cropper: { name: 'Cropper', template: '<div class="cropper-stub" />' } }));
vi.mock('vue-advanced-cropper/dist/style.css', () => ({}));

import Photos from '@/Pages/Photos/Index.vue';
import { useDialogHost } from '@/composables/useConfirm';

const photos = [
    { id: 1, name: 'a.jpg', url: '/r/1', thumb_url: '/t/1', date: '2026-06', date_label: 'June 2026', starred: false, taken_at: 200, added_at: 100, camera: 'Canon EOS R5', width: 4000, height: 3000 },
    { id: 2, name: 'b.jpg', url: '/r/2', thumb_url: '/t/2', date: '2026-06', date_label: 'June 2026', starred: true, taken_at: 100, added_at: 200, camera: null, width: null, height: null },
];

beforeEach(() => Object.values(s).forEach((f) => f.mockClear()));

describe('Photos/Index page', () => {
    it('renders the month timeline group in the shell without folder or detail panes', () => {
        const wrapper = mount(Photos, { props: { photos, albums: [], tags: [] } });
        expect(wrapper.text()).toContain('June 2026');
        expect(wrapper.findAll('.photo-thumb img').length).toBe(2);
        expect(wrapper.find('[data-pane="folders"]').exists()).toBe(false);
        expect(wrapper.find('[data-pane="detail"]').exists()).toBe(false);
    });

    it('shows an empty state with no photos', () => {
        const wrapper = mount(Photos, { props: { photos: [], albums: [], tags: [] } });
        expect(wrapper.text()).toContain('No photos here');
    });

    it('clicking an album card filters by that album', async () => {
        const wrapper = mount(Photos, { props: { photos, albums: [{ id: 8, name: 'Trip', count: 2 }], tags: [] } });
        const card = wrapper.findAll('div').find((d) => d.text().includes('Trip') && d.classes().includes('text-center'));
        await card!.trigger('click');
        expect(s.routerGet).toHaveBeenCalledWith('/photos', expect.objectContaining({ album: 8 }), expect.anything());
    });

    it('camera filter narrows the grid client-side', async () => {
        const wrapper = mount(Photos, { props: { photos, albums: [], tags: [] } });
        // Filters live behind the funnel toggle now.
        await wrapper.get('button[aria-label="Toggle filters"]').trigger('click');
        const select = wrapper.find('[data-stub="VibeFormSelect"]');
        await select.setValue('Canon EOS R5');
        expect(wrapper.findAll('.photo-thumb img').length).toBe(1);
        expect(wrapper.text()).toContain('Canon EOS R5');
    });

    it('starred-only toggle narrows the grid client-side', async () => {
        const wrapper = mount(Photos, { props: { photos, albums: [], tags: [] } });
        await wrapper.get('button[aria-label="Show starred only"]').trigger('click');
        expect(wrapper.findAll('.photo-thumb img').length).toBe(1);
    });

    it('name sort collapses the timeline into one flat group', async () => {
        const wrapper = mount(Photos, { props: { photos, albums: [], tags: [] } });
        const nameSort = wrapper.findAll('button.dd-item').find((b) => b.text().includes('Name A–Z'));
        await nameSort!.trigger('click');
        expect(wrapper.text()).toContain('All photos');
    });

    it('starring a photo posts to the star route', async () => {
        const wrapper = mount(Photos, { props: { photos, albums: [], tags: [] } });
        const starBtn = wrapper.findAll('button').find((b) => b.attributes('aria-label')?.startsWith('Star a.jpg'));
        await starBtn!.trigger('click');
        expect(s.routerPost).toHaveBeenCalledWith('/files/1/star', {}, expect.anything());
    });

    it('creating an album posts to /photos/albums', async () => {
        const wrapper = mount(Photos, { props: { photos, albums: [], tags: [] } });
        // The album-name field is the only text input; filling it enables Add.
        await wrapper.find('input[data-stub="VibeFormInput"]').setValue('Summer 2026');
        const create = wrapper.findAll('button').find((b) => b.text().trim() === 'Add');
        await create!.trigger('click');
        expect(s.formPost).toHaveBeenCalledWith('/photos/albums', expect.anything());
    });

    it('photo menu → Star posts to the star route', async () => {
        const wrapper = mount(Photos, { props: { photos, albums: [], tags: [] } });
        const star = wrapper.findAll('button.dd-item').find((b) => b.text().trim() === 'Star');
        await star!.trigger('click');
        expect(s.routerPost).toHaveBeenCalledWith('/files/1/star', {}, expect.anything());
    });

    it('photo menu → Delete confirms then deletes', async () => {
        const wrapper = mount(Photos, { props: { photos, albums: [], tags: [] } });
        const del = wrapper.findAll('button.dd-item').find((b) => b.text().trim() === 'Delete');
        await del!.trigger('click');
        const host = useDialogHost();
        expect(host.state.open).toBe(true);
        host.accept();
        await flushPromises();
        expect(s.routerDelete).toHaveBeenCalledWith('/delete/1', expect.anything());
    });

    it.each(['Open', 'Edit', 'Download'])('photo menu → %s runs without error', (label) => {
        const wrapper = mount(Photos, { props: { photos, albums: [], tags: [] } });
        const item = wrapper.findAll('button.dd-item').find((b) => b.text().trim() === label);
        expect(() => item!.trigger('click')).not.toThrow();
    });

    it('lightbox → Edit closes the lightbox before opening the editor (no stacked modals)', async () => {
        const wrapper = mount(Photos, { props: { photos, albums: [], tags: [] } });
        await wrapper.find('button.photo-thumb').trigger('click');
        const lightbox = wrapper.findComponent({ name: 'QuickLookModal' });
        expect(lightbox.props('modelValue')).toBe(true);

        lightbox.vm.$emit('action', { item: { action: 'edit' } });
        await wrapper.vm.$nextTick();

        // Two Bootstrap modals at the same z-index block every click in the
        // editor — the lightbox must close before the editor opens. The
        // Cropper is v-if'd on the editor being open.
        expect(lightbox.props('modelValue')).toBe(false);
        expect(wrapper.find('.cropper-stub').exists()).toBe(true);
    });

    it('lightbox prev/next step through photos', async () => {
        const wrapper = mount(Photos, { props: { photos, albums: [], tags: [] } });
        // Open the first photo into the lightbox.
        await wrapper.find('button.photo-thumb').trigger('click');
        const next = wrapper.findAll('button').find((b) => b.attributes('aria-label') === 'Next file');
        const prev = wrapper.findAll('button').find((b) => b.attributes('aria-label') === 'Previous file');
        await next!.trigger('click');
        await prev!.trigger('click');
        expect(next && prev).toBeTruthy();
    });

    it('deletes an album from the active-album header', async () => {
        const wrapper = mount(Photos, { props: { photos, albums: [{ id: 8, name: 'Trip', count: 2 }], tags: [], filters: { album: 8, tag: null } } });
        await wrapper.get('button[aria-label="Delete album Trip"]').trigger('click');
        const host = useDialogHost();
        expect(host.state.open).toBe(true);
        host.accept();
        await flushPromises();
        expect(s.routerDelete).toHaveBeenCalledWith('/photos/albums/8', expect.anything());
    });

    it('adds the selection to an album via the hover check', async () => {
        const wrapper = mount(Photos, { props: { photos, albums: [{ id: 8, name: 'Trip', count: 2 }], tags: [] } });
        await wrapper.get('button[aria-label="Select a.jpg"]').trigger('click');
        // The "Add to album" dropdown carries the album items.
        const dd = wrapper.findAllComponents({ name: 'VibeDropdown' }).find((c) => (c.props('items') as { id: number }[])?.some((i) => i.id === 8));
        dd!.vm.$emit('item-click', { item: { id: 8 } });
        await flushPromises();
        expect(s.routerPost).toHaveBeenCalledWith('/photos/albums/8/photos', { file_ids: [1] }, expect.anything());
    });

    it('batch delete confirms then posts the ids', async () => {
        const wrapper = mount(Photos, { props: { photos, albums: [], tags: [] } });
        await wrapper.get('button[aria-label="Select a.jpg"]').trigger('click');
        const del = wrapper.findAll('button').find((b) => b.text().includes('Delete'));
        await del!.trigger('click');
        const host = useDialogHost();
        expect(host.state.open).toBe(true);
        host.accept();
        await flushPromises();
        expect(s.routerPost).toHaveBeenCalledWith('/files/batch/delete', { ids: [1] }, expect.anything());
    });
});
