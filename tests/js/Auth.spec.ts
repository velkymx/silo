import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';

const { formPost, formReset } = vi.hoisted(() => ({ formPost: vi.fn(), formReset: vi.fn() }));
vi.mock('@inertiajs/vue3', () => ({
    router: { post: vi.fn(), get: vi.fn(), put: vi.fn(), delete: vi.fn(), visit: vi.fn() },
    usePage: () => ({ props: { auth: { user: null }, flash: {} } }),
    useForm: (data: Record<string, unknown>) => ({ ...data, processing: false, errors: {}, post: formPost, reset: formReset }),
    Link: { name: 'Link', template: '<a><slot /></a>' },
    Head: { name: 'Head', template: '<span><slot /></span>' },
}));

import Login from '@/Pages/Auth/Login.vue';
import Register from '@/Pages/Auth/Register.vue';

describe('Auth pages', () => {
    beforeEach(() => formPost.mockClear());

    it('Login submits to /login', async () => {
        const wrapper = mount(Login, { props: { groups: [] } });
        await wrapper.find('form').trigger('submit');
        expect(formPost).toHaveBeenCalledWith('/login', expect.anything());
    });

    it('Register submits to /register', async () => {
        const wrapper = mount(Register, { props: { groups: [{ id: 1, name: 'Staff' }] } });
        await wrapper.find('form').trigger('submit');
        expect(formPost).toHaveBeenCalledWith('/register', expect.anything());
    });
});
