import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { reactive } from 'vue';

const { formPatch, formReset, routerPost } = vi.hoisted(() => ({ formPatch: vi.fn(), formReset: vi.fn(), routerPost: vi.fn() }));
vi.mock('@inertiajs/vue3', () => ({
    router: { post: routerPost, get: vi.fn(), visit: vi.fn(), on: vi.fn(() => () => {}) },
    // reactive() so the page's computed (needsCurrentPassword) tracks field edits.
    useForm: (data: Record<string, unknown>) => reactive({ ...data, processing: false, errors: {}, patch: formPatch, reset: formReset }),
    usePage: () => ({ url: '/profile', props: { auth: { user: { id: 1, name: 'QA' } }, flash: {}, storage: { used: 0, quota: 0 } } }),
    Link: { name: 'Link', template: '<a><slot /></a>' },
}));
vi.mock('vue-advanced-cropper', () => ({ Cropper: { name: 'Cropper', template: '<div class="cropper-stub" />' } }));
vi.mock('vue-advanced-cropper/dist/style.css', () => ({}));

import Profile from '@/Pages/Profile/Edit.vue';

const user = { name: 'Ada Love', email: 'ada@x.test', avatar_url: null };

describe('Profile/Edit page', () => {
    beforeEach(() => { formPatch.mockClear(); routerPost.mockClear(); });

    it('renders a UserAvatar for the profile picture', () => {
        const wrapper = mount(Profile, { props: { user } });
        expect(wrapper.find('.user-avatar-stub').exists()).toBe(true);
    });

    it('submits the profile form to /profile', async () => {
        const wrapper = mount(Profile, { props: { user } });
        await wrapper.find('form').trigger('submit');
        expect(formPatch).toHaveBeenCalledWith('/profile', expect.anything());
    });

    it('reveals the current-password field after changing email', async () => {
        const wrapper = mount(Profile, { props: { user } });
        const before = wrapper.findAll('input[type=password]').length;
        const emailInput = wrapper.findAll('input[type=email]')[0];
        await emailInput.setValue('new@x.test');
        expect(wrapper.findAll('input[type=password]').length).toBe(before + 1);
    });

    it('Change photo opens the file picker', async () => {
        const wrapper = mount(Profile, { props: { user } });
        const fileInput = wrapper.find('input[type=file]').element as HTMLInputElement;
        const clickSpy = vi.spyOn(fileInput, 'click').mockImplementation(() => {});
        const btn = wrapper.findAll('button').find((b) => b.text().includes('Change photo'));
        await btn!.trigger('click');
        expect(clickSpy).toHaveBeenCalled();
    });

    it('applyCrop does not call router.post when toBlob returns null', async () => {
        // Cropper stub where toBlob calls back with null (simulates failed canvas export).
        const cropperWithNullBlob = {
            name: 'Cropper',
            template: '<div class="cropper-stub" />',
            methods: {
                getResult() {
                    return {
                        canvas: { toBlob: (cb: (b: Blob | null) => void) => cb(null) },
                    };
                },
            },
        };
        const wrapper = mount(Profile, {
            props: { user },
            global: { stubs: { Cropper: cropperWithNullBlob } },
        });
        const btn = wrapper.findAll('button').find((b) => b.text().includes('Use photo'));
        await btn!.trigger('click');
        expect(routerPost).not.toHaveBeenCalled();
    });

    it('applyCrop calls router.post when canvas and blob are valid', async () => {
        const mockBlob = new Blob(['x'], { type: 'image/jpeg' });
        const cropperOk = {
            name: 'Cropper',
            template: '<div class="cropper-stub" />',
            methods: {
                getResult() {
                    return {
                        canvas: { toBlob: (cb: (b: Blob | null) => void) => cb(mockBlob) },
                    };
                },
            },
        };
        const wrapper = mount(Profile, {
            props: { user },
            global: { stubs: { Cropper: cropperOk } },
        });
        const btn = wrapper.findAll('button').find((b) => b.text().includes('Use photo'));
        await btn!.trigger('click');
        expect(routerPost).toHaveBeenCalledWith('/profile/avatar', expect.anything(), expect.anything());
    });
});
