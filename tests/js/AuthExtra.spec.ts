import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';

const { formPost, formReset } = vi.hoisted(() => ({ formPost: vi.fn(), formReset: vi.fn() }));
vi.mock('@inertiajs/vue3', () => ({
    useForm: (data: Record<string, unknown>) => ({ ...data, processing: false, errors: {}, post: formPost, reset: formReset }),
    Link: { name: 'Link', template: '<a><slot /></a>' },
    Head: { name: 'Head', template: '<span><slot /></span>' },
}));

import ConfirmPassword from '@/Pages/Auth/ConfirmPassword.vue';
import ForgotPassword from '@/Pages/Auth/ForgotPassword.vue';
import ResetPassword from '@/Pages/Auth/ResetPassword.vue';

describe('Auth (extra) pages', () => {
    beforeEach(() => { formPost.mockClear(); formReset.mockClear(); });

    it('ConfirmPassword submits to /password/confirm', async () => {
        const wrapper = mount(ConfirmPassword);
        await wrapper.find('form').trigger('submit');
        expect(formPost).toHaveBeenCalledWith('/password/confirm', expect.anything());
    });

    it('ForgotPassword submits to /password/email and shows status', async () => {
        const wrapper = mount(ForgotPassword, { props: { status: 'Link sent!' } });
        expect(wrapper.text()).toContain('Link sent!');
        await wrapper.find('form').trigger('submit');
        expect(formPost).toHaveBeenCalledWith('/password/email');
    });

    it('ResetPassword seeds token/email and submits to /password/reset', async () => {
        const wrapper = mount(ResetPassword, { props: { token: 'tok-123', email: 'a@b.test' } });
        await wrapper.find('form').trigger('submit');
        expect(formPost).toHaveBeenCalledWith('/password/reset', expect.anything());
    });
});
