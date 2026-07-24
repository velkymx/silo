import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import ResourceModal from '@/Components/ResourceModal.vue';

function makeForm(overrides: Record<string, unknown> = {}) {
    return {
        processing: false,
        errors: {},
        reset: vi.fn(),
        clearErrors: vi.fn(),
        post: vi.fn(),
        put: vi.fn(),
        ...overrides,
    };
}

const storeUrl = '/items';
const updateUrl = (id: number) => `/items/${id}`;

describe('ResourceModal', () => {
    it('renders slot fields', async () => {
        const form = makeForm();
        const wrapper = mount(ResourceModal, {
            props: { form, storeUrl, updateUrl, addTitle: 'Add item', editTitle: 'Edit item' },
            slots: { default: '<input data-testid="field" />' },
        });
        await wrapper.vm.openAdd();
        expect(wrapper.find('[data-testid="field"]').exists()).toBe(true);
    });

    it('calls form.post with storeUrl when editingId is null', async () => {
        const form = makeForm();
        const wrapper = mount(ResourceModal, {
            props: { form, storeUrl, updateUrl, addTitle: 'Add item', editTitle: 'Edit item' },
        });
        await wrapper.vm.openAdd();
        await wrapper.findAll('button').find((b) => b.text() === 'Add')!.trigger('click');
        expect(form.post).toHaveBeenCalledWith(storeUrl, expect.objectContaining({ preserveScroll: true }));
        expect(form.put).not.toHaveBeenCalled();
    });

    it('calls form.put with updateUrl(id) when editingId is set', async () => {
        const form = makeForm();
        const wrapper = mount(ResourceModal, {
            props: { form, storeUrl, updateUrl, addTitle: 'Add item', editTitle: 'Edit item' },
        });
        await wrapper.vm.openEdit({ id: 7, name: 'test' });
        await wrapper.findAll('button').find((b) => b.text() === 'Save')!.trigger('click');
        expect(form.put).toHaveBeenCalledWith('/items/7', expect.objectContaining({ preserveScroll: true }));
        expect(form.post).not.toHaveBeenCalled();
    });

    it('cancel resets form and clears errors', async () => {
        const form = makeForm();
        const wrapper = mount(ResourceModal, {
            props: { form, storeUrl, updateUrl, addTitle: 'Add item', editTitle: 'Edit item' },
        });
        await wrapper.vm.openAdd();
        await wrapper.findAll('button').find((b) => b.text() === 'Cancel')!.trigger('click');
        expect(form.reset).toHaveBeenCalled();
        expect(form.clearErrors).toHaveBeenCalled();
    });

    it('emits open-edit with the item when openEdit is called', async () => {
        const form = makeForm();
        const wrapper = mount(ResourceModal, {
            props: { form, storeUrl, updateUrl, addTitle: 'Add item', editTitle: 'Edit item' },
        });
        const item = { id: 3, name: 'foo' };
        await wrapper.vm.openEdit(item);
        expect(wrapper.emitted('open-edit')?.[0]).toEqual([item]);
    });

    it('shows Add button label when creating', async () => {
        const form = makeForm();
        const wrapper = mount(ResourceModal, {
            props: { form, storeUrl, updateUrl, addTitle: 'Add widget', editTitle: 'Edit widget' },
        });
        await wrapper.vm.openAdd();
        const buttons = wrapper.findAll('button').map((b) => b.text());
        expect(buttons).toContain('Add');
    });

    it('shows Save button label when editing', async () => {
        const form = makeForm();
        const wrapper = mount(ResourceModal, {
            props: { form, storeUrl, updateUrl, addTitle: 'Add widget', editTitle: 'Edit widget' },
        });
        await wrapper.vm.openEdit({ id: 1 });
        const buttons = wrapper.findAll('button').map((b) => b.text());
        expect(buttons).toContain('Save');
    });
});
