import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';

const { formPatch } = vi.hoisted(() => ({ formPatch: vi.fn() }));
vi.mock('@inertiajs/vue3', () => ({
    useForm: (data: Record<string, unknown>) => ({ ...data, processing: false, errors: {}, patch: formPatch, clearErrors: vi.fn() }),
}));

import RenameModal from '@/Components/RenameModal.vue';

describe('RenameModal', () => {
    beforeEach(() => formPatch.mockClear());

    it('seeds the name from the item and patches the rename route', async () => {
        const wrapper = mount(RenameModal, { props: { modelValue: false, item: { id: 8, name: 'old.txt' } } });
        await wrapper.setProps({ modelValue: true });
        expect((wrapper.find('input').element as HTMLInputElement).value).toBe('old.txt');
        const btn = wrapper.findAll('button').find((b) => b.text() === 'Rename');
        await btn!.trigger('click');
        expect(formPatch).toHaveBeenCalledWith('/files/8/rename', expect.anything());
    });
});
