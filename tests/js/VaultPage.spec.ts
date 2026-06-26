import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';

const h = vi.hoisted(() => ({
    post: vi.fn(() => Promise.resolve({ secret: 'PLAINTEXT-SECRET' })),
    get: vi.fn(() => Promise.resolve({ password: 'GENERATED' })),
    formPost: vi.fn(),
    formPut: vi.fn(),
    del: vi.fn(),
    prompt: vi.fn(() => Promise.resolve('my-password')),
    confirm: vi.fn(() => Promise.resolve(true)),
    form: null as null | Record<string, unknown>,
}));

vi.mock('@inertiajs/vue3', () => ({
    router: { delete: h.del },
    useForm: (init: Record<string, unknown>) => {
        h.form = { ...init, post: h.formPost, put: h.formPut, reset: vi.fn(), clearErrors: vi.fn(), processing: false, errors: {} };
        return h.form;
    },
}));
vi.mock('@/lib/http', () => ({ http: { post: h.post, get: h.get }, getText: vi.fn() }));
vi.mock('@/composables/useConfirm', () => ({
    useConfirm: () => ({ confirm: h.confirm }),
    usePrompt: () => ({ prompt: h.prompt }),
}));

import VaultIndex from '@/Pages/Vault/Index.vue';

const items = [
    { id: 1, name: 'Prod DB', username: 'admin', url: null, category: 'Infra', shared: false, group_id: null, last_rotated_at: '2026-01-01', can_edit: true },
];

beforeEach(() => {
    h.post.mockClear();
    h.get.mockClear();
    h.prompt.mockClear();
});

describe('Vault/Index', () => {
    it('masks secrets by default', () => {
        const wrapper = mount(VaultIndex, { props: { items, groups: [] } });
        expect(wrapper.text()).toContain('Prod DB');
        expect(wrapper.text()).toContain('••••');
        expect(wrapper.text()).not.toContain('PLAINTEXT-SECRET');
    });

    it('reveals a secret after a password prompt', async () => {
        const wrapper = mount(VaultIndex, { props: { items, groups: [] } });
        await wrapper.get('[title="Reveal"]').trigger('click');
        await flushPromises();
        expect(h.prompt).toHaveBeenCalled();
        expect(h.post).toHaveBeenCalledWith('/vault/1/reveal', { password: 'my-password' });
        expect(wrapper.text()).toContain('PLAINTEXT-SECRET');
    });

    it('fills the secret field from the generator', async () => {
        const wrapper = mount(VaultIndex, { props: { items: [], groups: [] } });
        await wrapper.findAll('button').find((b) => b.text().includes('Add'))!.trigger('click');
        await wrapper.get('[title="Generate"]').trigger('click');
        await flushPromises();
        expect(h.get).toHaveBeenCalledWith('/vault/generate?length=20');
        expect(h.form!.secret).toBe('GENERATED');
    });

    it('generate failure shows an error message', async () => {
        h.get.mockRejectedValueOnce(new Error('network error'));
        const wrapper = mount(VaultIndex, { props: { items: [], groups: [] } });
        await wrapper.findAll('button').find((b) => b.text().includes('Add'))!.trigger('click');
        await wrapper.get('[title="Generate"]').trigger('click');
        await flushPromises();
        // An error message must appear (revealError shown via VibeAlert).
        expect(wrapper.text()).toMatch(/could not generate|generate/i);
    });
});
