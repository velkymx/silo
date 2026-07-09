import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';

const r = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn() }));
vi.mock('@inertiajs/vue3', () => ({ router: { get: r.get, post: r.post } }));

import QuickActions from '@/Components/Dashboard/QuickActions.vue';

beforeEach(() => {
    r.get.mockClear();
    r.post.mockClear();
});

describe('QuickActions', () => {
    it('renders the four actions', () => {
        const wrapper = mount(QuickActions);
        expect(wrapper.text()).toContain('Upload File');
        expect(wrapper.text()).toContain('New Note');
        expect(wrapper.text()).toContain('Save Bookmark');
        expect(wrapper.text()).toContain('Add Secret');
    });

    it('upload deep-links to the files surface', async () => {
        const wrapper = mount(QuickActions);
        await wrapper.find('[data-action="upload"]').trigger('click');
        expect(r.get).toHaveBeenCalledWith('/', { upload: 1 });
    });

    it('new note posts to /notes', async () => {
        const wrapper = mount(QuickActions);
        await wrapper.find('[data-action="note"]').trigger('click');
        expect(r.post).toHaveBeenCalledWith('/notes', { name: 'Untitled' });
    });

    it('save bookmark deep-links to /bookmarks with ?new', async () => {
        const wrapper = mount(QuickActions);
        await wrapper.find('[data-action="bookmark"]').trigger('click');
        expect(r.get).toHaveBeenCalledWith('/bookmarks', { new: 1 });
    });

    it('add secret deep-links to /vault with ?new', async () => {
        const wrapper = mount(QuickActions);
        await wrapper.find('[data-action="secret"]').trigger('click');
        expect(r.get).toHaveBeenCalledWith('/vault', { new: 1 });
    });
});
