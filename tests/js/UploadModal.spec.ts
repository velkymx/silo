import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';

const { formPost, formReset } = vi.hoisted(() => ({ formPost: vi.fn(), formReset: vi.fn() }));
vi.mock('@inertiajs/vue3', () => ({
    useForm: (data: Record<string, unknown>) => ({ ...data, processing: false, errors: {}, progress: null, post: formPost, reset: formReset }),
}));

import UploadModal from '@/Components/UploadModal.vue';

const png = () => new File(['x'], 'pic.png', { type: 'image/png' });

describe('UploadModal', () => {
    beforeEach(() => { formPost.mockClear(); formReset.mockClear(); });

    it('shows the per-file size cap label', () => {
        const wrapper = mount(UploadModal, { props: { modelValue: true, parentId: 1, maxUploadKb: 2048 } });
        expect(wrapper.text()).toContain('2 MB');
    });

    it('dropping files lists them and enables Upload', async () => {
        const wrapper = mount(UploadModal, { props: { modelValue: true, parentId: null, maxUploadKb: 1024 } });
        await wrapper.find('.upload-dropzone').trigger('drop', { dataTransfer: { files: [png()] } });
        expect(wrapper.text()).toContain('pic.png');
        expect(wrapper.text()).toContain('1 file(s)');
        const upBtn = wrapper.findAll('button').find((b) => b.text().includes('Upload'));
        expect(upBtn!.attributes('disabled')).toBeUndefined();
    });

    it('removing a file empties the list', async () => {
        const wrapper = mount(UploadModal, { props: { modelValue: true, parentId: null, maxUploadKb: 1024 } });
        await wrapper.find('.upload-dropzone').trigger('drop', { dataTransfer: { files: [png()] } });
        const xBtn = wrapper.findAll('button').find((b) => b.html().includes('x-lg'));
        await xBtn!.trigger('click');
        expect(wrapper.text()).not.toContain('pic.png');
    });

    it('does not mutate the native File object with a __url property', async () => {
        const file = png();
        const wrapper = mount(UploadModal, { props: { modelValue: true, parentId: null, maxUploadKb: 1024 } });
        await wrapper.find('.upload-dropzone').trigger('drop', { dataTransfer: { files: [file] } });
        expect('__url' in file).toBe(false);
        expect((file as unknown as Record<string, unknown>).__url).toBeUndefined();
    });

    it('submit posts to /upload with the parent id', async () => {
        const wrapper = mount(UploadModal, { props: { modelValue: true, parentId: 42, maxUploadKb: 1024 } });
        await wrapper.find('.upload-dropzone').trigger('drop', { dataTransfer: { files: [png()] } });
        const upBtn = wrapper.findAll('button').find((b) => b.text().includes('Upload'));
        await upBtn!.trigger('click');
        expect(formPost).toHaveBeenCalledWith('/upload', expect.objectContaining({ forceFormData: true }));
    });
});
