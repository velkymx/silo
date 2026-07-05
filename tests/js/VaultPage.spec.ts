import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises, VueWrapper } from '@vue/test-utils';

const h = vi.hoisted(() => ({
    post: vi.fn(() => Promise.resolve({ secret: 'PLAINTEXT-SECRET' })),
    get: vi.fn(() => Promise.resolve({ password: 'GENERATED' })),
    formPost: vi.fn(),
    formPut: vi.fn(),
    del: vi.fn(),
    routerPost: vi.fn(),
    prompt: vi.fn(() => Promise.resolve('my-password')),
    confirm: vi.fn(() => Promise.resolve(true)),
    form: null as null | Record<string, unknown>,
}));

vi.mock('@inertiajs/vue3', () => ({
    router: { delete: h.del, post: h.routerPost, visit: vi.fn(), on: vi.fn(() => vi.fn()) },
    useForm: (init: Record<string, unknown>) => {
        h.form = { ...init, post: h.formPost, put: h.formPut, reset: vi.fn(), clearErrors: vi.fn(), processing: false, errors: {} };
        return h.form;
    },
    usePage: () => ({ url: '/vault', props: { auth: { user: { id: 1, name: 'QA' } }, storage: { used: 0, quota: 0 } } }),
    Link: { name: 'Link', template: '<a><slot /></a>' },
}));
vi.mock('@/lib/http', () => ({ http: { post: h.post, get: h.get }, getText: vi.fn() }));
vi.mock('@/composables/useConfirm', () => ({
    useConfirm: () => ({ confirm: h.confirm }),
    usePrompt: () => ({ prompt: h.prompt }),
    useDialogHost: () => ({
        state: { open: false, mode: 'confirm', title: '', message: '', inputValue: '', placeholder: '', confirmLabel: 'OK', cancelLabel: 'Cancel', variant: 'primary' },
        accept: vi.fn(),
        cancel: vi.fn(),
    }),
}));

import VaultIndex from '@/Pages/Vault/Index.vue';
import { useToast } from '@/composables/useToast';

const items = [
    { id: 1, name: 'Prod DB', username: 'admin', url: null, category: 'Infra', shared: false, group_id: null, last_rotated_at: '2026-01-01', can_edit: true },
];

function selectRow(wrapper: VueWrapper, id: number) {
    const table = wrapper.findComponent({ name: 'VibeDataTable' });
    const row = (table.props('items') as Array<{ id: number }>).find((i) => i.id === id);
    table.vm.$emit('row-clicked', row, 0);
}

async function mountWithSelection() {
    const wrapper = mount(VaultIndex, { props: { items, groups: [] } });
    selectRow(wrapper, 1);
    await wrapper.vm.$nextTick();
    return wrapper;
}

beforeEach(() => {
    h.post.mockClear();
    h.get.mockClear();
    h.prompt.mockClear();
    useToast().state.items.splice(0); // clear module-level toast state
});

describe('Vault/Index', () => {
    it('lists secrets in the table with folders in the sidebar', () => {
        const wrapper = mount(VaultIndex, { props: { items, groups: [] } });
        expect(wrapper.findComponent({ name: 'VibeDataTable' }).exists()).toBe(true);
        expect(wrapper.text()).toContain('Prod DB');
        expect(wrapper.text()).toContain('All Secrets');
        expect(wrapper.text()).toContain('Infra');
    });

    it('masks the secret in the detail pane by default', async () => {
        const wrapper = await mountWithSelection();
        expect(wrapper.get('[data-pane="detail"]').text()).toContain('••••');
        expect(wrapper.text()).not.toContain('PLAINTEXT-SECRET');
    });

    it('reveals a secret after a password prompt', async () => {
        const wrapper = await mountWithSelection();
        await wrapper.get('[aria-label="Reveal secret"]').trigger('click');
        await flushPromises();
        expect(h.prompt).toHaveBeenCalled();
        expect(h.post).toHaveBeenCalledWith('/vault/1/reveal', { password: 'my-password' });
        const secretInput = wrapper.find('input.vault-secret');
        expect(secretInput.exists()).toBe(true);
        expect((secretInput.element as HTMLInputElement).value).toBe('PLAINTEXT-SECRET');
    });

    it('fills the secret field from the generator', async () => {
        const wrapper = mount(VaultIndex, { props: { items: [], groups: [] } });
        await wrapper.get('[title="Add secret"]').trigger('click');
        await wrapper.get('[title="Generate"]').trigger('click');
        await flushPromises();
        expect(h.get).toHaveBeenCalledWith('/vault/generate?length=20');
        expect(h.form!.secret).toBe('GENERATED');
    });

    it('generate failure shows an error message', async () => {
        h.get.mockRejectedValueOnce(new Error('network error'));
        const wrapper = mount(VaultIndex, { props: { items: [], groups: [] } });
        await wrapper.get('[title="Add secret"]').trigger('click');
        await wrapper.get('[title="Generate"]').trigger('click');
        await flushPromises();
        // An error message must appear (revealError shown via VibeAlert).
        expect(wrapper.text()).toMatch(/could not generate|generate/i);
    });

    it('HI-12: auto-hide timer is cancelled on unmount — no post-unmount state mutation', async () => {
        vi.useFakeTimers();
        h.post.mockResolvedValueOnce({ secret: 'S3CR3T' });
        h.prompt.mockResolvedValueOnce('pw');
        const wrapper = await mountWithSelection();
        await wrapper.get('[aria-label="Reveal secret"]').trigger('click');
        await flushPromises();
        // Secret revealed — 20s auto-hide timer is running.
        wrapper.unmount();
        // Advance past the 20s — must not throw or try to mutate dead state.
        expect(() => vi.runAllTimers()).not.toThrow();
        vi.useRealTimers();
    });

    it('ME-22: copy button shows a success toast when clipboard write succeeds', async () => {
        const writeText = vi.fn(() => Promise.resolve());
        Object.defineProperty(navigator, 'clipboard', { value: { writeText }, writable: true, configurable: true });

        const wrapper = await mountWithSelection();
        await wrapper.get('[aria-label="Reveal secret"]').trigger('click');
        await flushPromises();
        await wrapper.get('[aria-label="Copy secret to clipboard"]').trigger('click');
        await flushPromises();

        expect(writeText).toHaveBeenCalledWith('PLAINTEXT-SECRET');
        const { state } = useToast();
        expect(state.items.some((t) => /copied/i.test(t.text))).toBe(true);
    });

    it('ME-22: copy button shows a danger toast when clipboard is blocked', async () => {
        Object.defineProperty(navigator, 'clipboard', {
            value: { writeText: vi.fn(() => Promise.reject(new DOMException('denied'))) },
            writable: true, configurable: true,
        });

        const wrapper = await mountWithSelection();
        await wrapper.get('[aria-label="Reveal secret"]').trigger('click');
        await flushPromises();
        await wrapper.get('[aria-label="Copy secret to clipboard"]').trigger('click');
        await flushPromises();

        const { state } = useToast();
        expect(state.items.some((t) => /clipboard|could not copy/i.test(t.text))).toBe(true);
    });

    it('revealed secret is not inside a text-truncate container', async () => {
        const wrapper = await mountWithSelection();
        await wrapper.get('[aria-label="Reveal secret"]').trigger('click');
        await flushPromises();
        // The element containing the revealed secret must NOT have text-truncate.
        const secretEl = wrapper.find('input.vault-secret, code.vault-secret, pre.vault-secret');
        expect(secretEl.exists()).toBe(true);
        // Its closest ancestor with text-truncate class must not exist.
        expect(secretEl.element.closest('.text-truncate')).toBeNull();
    });
});
