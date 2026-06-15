import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';

const { formPost, formReset } = vi.hoisted(() => ({ formPost: vi.fn(), formReset: vi.fn() }));
vi.mock('@inertiajs/vue3', () => ({
    useForm: (data: Record<string, unknown>) => ({ ...data, processing: false, errors: {}, post: formPost, reset: formReset }),
    Head: { name: 'Head', template: '<span><slot /></span>' },
}));

import Share from '@/Pages/Public/Share.vue';

describe('Public/Share page', () => {
    beforeEach(() => { formPost.mockClear(); formReset.mockClear(); });

    it('locked link submits the unlock form', async () => {
        const wrapper = mount(Share, { props: { locked: true, token: 'abc' } });
        expect(wrapper.text()).toContain('password-protected');
        await wrapper.find('form').trigger('submit');
        expect(formPost).toHaveBeenCalledWith('/s/abc/unlock', expect.anything());
    });

    it('unlocked previewable image shows preview + download', () => {
        const wrapper = mount(Share, {
            props: { locked: false, token: 'abc', name: 'pic.png', size: 2048, type: 'png', mime: 'image/png', previewable: true, preview_url: '/p/abc', download_url: '/d/abc' },
        });
        expect(wrapper.find('img').attributes('src')).toBe('/p/abc');
        const dl = wrapper.findAll('a,button').find((b) => b.text().includes('Download'));
        expect(dl).toBeTruthy();
    });

    it('download-disabled link shows the disabled note', () => {
        const wrapper = mount(Share, { props: { locked: false, token: 'abc', name: 'doc.pdf', previewable: false } });
        expect(wrapper.text()).toContain('Downloads are disabled');
    });
});
