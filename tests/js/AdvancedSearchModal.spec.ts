import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';

const { routerGet, routerPost } = vi.hoisted(() => ({ routerGet: vi.fn(), routerPost: vi.fn() }));
vi.mock('@inertiajs/vue3', () => ({ router: { get: routerGet, post: routerPost } }));

import AdvancedSearchModal from '@/Components/AdvancedSearchModal.vue';

const allTags = [{ id: 9, name: 'work' }];

describe('AdvancedSearchModal', () => {
    beforeEach(() => { routerGet.mockClear(); routerPost.mockClear(); });

    it('seeds the form from filters when opened', async () => {
        const wrapper = mount(AdvancedSearchModal, {
            props: { modelValue: false, filters: { search: 'q', ftype: 'pdf' }, allTags },
        });
        await wrapper.setProps({ modelValue: true });
        expect(wrapper.find('input').element).toBeTruthy();
        expect(wrapper.html()).toContain('Advanced Search');
    });

    it('Search navigates with the filled params', async () => {
        const wrapper = mount(AdvancedSearchModal, {
            props: { modelValue: false, filters: { search: 'report' }, allTags },
        });
        await wrapper.setProps({ modelValue: true });
        const btn = wrapper.findAll('button').find((b) => b.text().includes('Search'));
        await btn!.trigger('click');
        expect(routerGet).toHaveBeenCalledWith('/', { search: 'report' }, expect.anything());
    });

    it('Save as Smart Folder posts the name + params', async () => {
        vi.spyOn(window, 'prompt').mockReturnValue('My Folder');
        const wrapper = mount(AdvancedSearchModal, {
            props: { modelValue: false, filters: { ftype: 'image' }, allTags },
        });
        await wrapper.setProps({ modelValue: true });
        const btn = wrapper.findAll('button').find((b) => b.text().includes('Smart Folder'));
        await btn!.trigger('click');
        expect(routerPost).toHaveBeenCalledWith('/saved-searches', { name: 'My Folder', params: { ftype: 'image' } }, expect.anything());
    });
});
