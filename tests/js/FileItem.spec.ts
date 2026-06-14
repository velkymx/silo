import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import FileItem from '@/Components/FileItem.vue';

const file = {
    id: 7, name: 'photo.png', type: 'png', is_dir: false, starred: false,
    thumb_url: '/t/7', size: 2048, status: 'ready', version: 1, tags: [{ id: 3, name: 'work', color: '#f00' }],
};
const menu = [{ text: 'Delete', icon: 'trash', action: 'delete' }];

describe('FileItem', () => {
    it('grid view renders a card and emits open on click', async () => {
        const wrapper = mount(FileItem, { props: { item: file, view: 'grid', selected: false, menu } });
        expect(wrapper.find('.card').exists()).toBe(true);
        expect(wrapper.text()).toContain('photo.png');
        await wrapper.find('.card').trigger('click');
        expect(wrapper.emitted('open')?.[0]?.[0]).toMatchObject({ id: 7 });
    });

    it('grid view forwards star + action from ItemActions', async () => {
        const wrapper = mount(FileItem, { props: { item: file, view: 'grid', menu } });
        const starBtn = wrapper.findAll('button').find((b) => b.html().includes('star'));
        await starBtn!.trigger('click');
        expect(wrapper.emitted('star')).toBeTruthy();

        await wrapper.find('.dd-item').trigger('click');
        expect(wrapper.emitted('action')).toBeTruthy();
    });

    it('list view renders the name cell + status/version badges', () => {
        const f = { ...file, status: 'pending', version: 3 };
        const wrapper = mount(FileItem, { props: { item: f, view: 'list', selected: true } });
        expect(wrapper.find('.card').exists()).toBe(false);
        expect(wrapper.text()).toContain('photo.png');
        expect(wrapper.text()).toContain('Processing');
        expect(wrapper.text()).toContain('v3');
    });

    it('list view emits tag + toggle-select', async () => {
        const wrapper = mount(FileItem, { props: { item: file, view: 'list' } });
        await wrapper.find('.badge.rounded-pill').trigger('click');
        expect(wrapper.emitted('tag')?.[0]).toEqual([3]);
        await wrapper.find('.select-check').trigger('click');
        expect(wrapper.emitted('toggle-select')?.[0]).toEqual([7]);
    });

    it('renders a colored icon when no thumbnail', () => {
        const wrapper = mount(FileItem, { props: { item: { id: 1, name: 'x.zip', type: 'zip' }, view: 'list' } });
        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.find('i.bi').exists()).toBe(true);
    });
});
