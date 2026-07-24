import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { mount, flushPromises, enableAutoUnmount } from '@vue/test-utils';

// Each palette instance attaches a document keydown listener; unmount between
// tests so a stale instance never handles another test's keystrokes.
enableAutoUnmount(afterEach);

const s = vi.hoisted(() => ({ get: vi.fn(), visit: vi.fn(), delete: vi.fn() }));
const page = { url: '/', props: {} as Record<string, unknown> };
vi.mock('@inertiajs/vue3', () => ({
    router: { get: s.get, visit: s.visit, delete: s.delete },
    usePage: () => page,
}));
vi.mock('@velkymx/vibeui', () => ({ useColorMode: () => ({ toggleColorMode: vi.fn() }) }));

import CommandPalette from '@/Components/CommandPalette.vue';
import { useCommandPalette } from '@/composables/useCommandPalette';

function mockFetch(results: Record<string, unknown[]>, scope = 'files') {
    const fn = vi.fn().mockResolvedValue({
        ok: true,
        json: async () => ({ scope, results }),
    });
    vi.stubGlobal('fetch', fn);
    return fn;
}

async function openPalette() {
    const wrapper = mount(CommandPalette);
    useCommandPalette().open();
    await flushPromises();
    return wrapper;
}

beforeEach(() => {
    page.url = '/';
    Object.values(s).forEach((f) => f.mockClear());
    useCommandPalette().close();
});
afterEach(() => vi.unstubAllGlobals());

describe('CommandPalette', () => {
    it('shows commands and no results before a query is typed', async () => {
        const fetchFn = mockFetch({});
        const wrapper = await openPalette();
        expect(wrapper.text()).toContain('Navigation');
        expect(wrapper.text()).toContain('Go to Files');
        expect(fetchFn).not.toHaveBeenCalled();
    });

    it('defaults the scope to the current surface', async () => {
        page.url = '/rss';
        mockFetch({}, 'rss');
        const wrapper = await openPalette();
        // The area button is labelled for the RSS surface (Articles) and active.
        const area = wrapper.get('[data-scope="area"]');
        expect(area.text()).toBe('Articles');
        expect(area.classes()).toContain('btn-primary');
    });

    it('maps directory and admin-users routes to the People scope', async () => {
        page.url = '/directory';
        mockFetch({}, 'people');
        const wrapper = await openPalette();
        expect(wrapper.get('[data-scope="area"]').text()).toBe('People');
        wrapper.unmount();

        page.url = '/users';
        const wrapper2 = await openPalette();
        expect(wrapper2.get('[data-scope="area"]').text()).toBe('People');
    });

    it('debounces a fetch and renders grouped results', async () => {
        vi.useFakeTimers();
        const fetchFn = mockFetch({ files: [{ id: 1, title: 'Budget.xlsx', snippet: null, url: '/?folder=2&selected=1' }] });
        const wrapper = mount(CommandPalette);
        useCommandPalette().open();
        await flushPromises();

        await wrapper.find('input').setValue('budget');
        await vi.runAllTimersAsync();
        await flushPromises();
        vi.useRealTimers();

        expect(fetchFn).toHaveBeenCalledTimes(1);
        expect(fetchFn.mock.calls[0][0]).toContain('q=budget');
        expect(fetchFn.mock.calls[0][0]).toContain('scope=files');
        expect(wrapper.text()).toContain('Budget.xlsx');
    });

    it('navigates to the focused internal result on Enter', async () => {
        vi.useFakeTimers();
        mockFetch({ files: [{ id: 1, title: 'Budget.xlsx', snippet: null, url: '/?folder=2&selected=1' }] });
        const wrapper = mount(CommandPalette, { attachTo: document.body });
        useCommandPalette().open();
        await flushPromises();
        await wrapper.find('input').setValue('budget');
        await vi.runAllTimersAsync();
        await flushPromises();
        vi.useRealTimers();

        // The first result is focused by default; Enter opens it.
        document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter' }));
        await flushPromises();
        expect(s.get).toHaveBeenCalledWith('/?folder=2&selected=1');
        wrapper.unmount();
    });

    it('runs a matching command on click', async () => {
        mockFetch({});
        const wrapper = mount(CommandPalette, { attachTo: document.body });
        useCommandPalette().open();
        await flushPromises();

        const row = wrapper.findAll('.command-item').find((r) => r.text().includes('Go to Files'))!;
        await row.trigger('click');
        expect(s.get).toHaveBeenCalledWith('/');
        wrapper.unmount();
    });

    it('refetches when the scope toggles to all', async () => {
        vi.useFakeTimers();
        const fetchFn = mockFetch({ files: [] });
        const wrapper = mount(CommandPalette);
        useCommandPalette().open();
        await flushPromises();
        await wrapper.find('input').setValue('term');
        await vi.runAllTimersAsync();
        await flushPromises();

        await wrapper.get('[data-scope="all"]').trigger('click');
        await vi.runAllTimersAsync();
        await flushPromises();
        vi.useRealTimers();

        expect(fetchFn.mock.calls.some((c) => String(c[0]).includes('scope=all'))).toBe(true);
    });
});
