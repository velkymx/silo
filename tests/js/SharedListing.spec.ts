import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';

const { routerGet } = vi.hoisted(() => ({ routerGet: vi.fn() }));
vi.mock('@inertiajs/vue3', () => ({ router: { get: routerGet } }));

import SharedListing from '@/Components/SharedListing.vue';

const folders = [
    { id: 11, name: 'Team', owner: 'Alice', abilities: ['view', 'download'] },
];
const files = [
    { id: 21, name: 'photo.png', owner: 'Alice', size: 2048, type: 'png', abilities: ['view'], thumb_url: '/t/21', url: '/raw/21' },
    { id: 22, name: 'doc.pdf', owner: 'Bob', size: 4096, type: 'pdf', abilities: ['view', 'download'], url: '/raw/22' },
];

describe('SharedListing', () => {
    beforeEach(() => routerGet.mockClear());

    it('renders shared folders and files', () => {
        const wrapper = mount(SharedListing, { props: { folders, files } });
        expect(wrapper.text()).toContain('Team');
        expect(wrapper.text()).toContain('photo.png');
        expect(wrapper.text()).toContain('doc.pdf');
    });

    it('opening a shared folder navigates to /shared/{id}', async () => {
        const wrapper = mount(SharedListing, { props: { folders, files } });
        const openBtn = wrapper.findAll('button').find((b) => b.text().includes('Open'));
        await openBtn!.trigger('click');
        expect(routerGet).toHaveBeenCalledWith('/shared/11', {}, expect.anything());
    });

    it('quick-look opens the preview modal for a previewable file', async () => {
        const wrapper = mount(SharedListing, { props: { folders, files } });
        // The thumbnail image is clickable → opens Quick Look.
        await wrapper.find('img').trigger('click');
        expect(wrapper.html()).toContain('/raw/21');
    });
});
