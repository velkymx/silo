import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { ref } from 'vue';

const s = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), del: vi.fn(), visit: vi.fn(), toggleColorMode: vi.fn() }));
const page = { url: '/', props: {} as Record<string, unknown> };
vi.mock('@inertiajs/vue3', () => ({
    router: { get: s.get, post: s.post, delete: s.del, visit: s.visit },
    usePage: () => page,
    Link: { template: '<a><slot /></a>' },
}));
vi.mock('@velkymx/vibeui', () => ({
    useColorMode: () => ({ colorMode: ref('auto'), toggleColorMode: s.toggleColorMode }),
    useBreakpoints: () => ({ isMobile: ref(false) }),
}));

import ShellLayout from '@/Layouts/ShellLayout.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';

function mountShell(props: Record<string, unknown> = {}) {
    page.url = (props.url as string) ?? '/';
    page.props = {
        auth: { user: { id: 1, name: 'Ada Lovelace', is_admin: true } },
        flash: {},
        storage: { used: 5 * 1024 * 1024, quota: 10 * 1024 * 1024 },
        currentFolder: { id: 12 },
        ...props,
    };
    return mount(ShellLayout, { slots: { contents: '<p>page body</p>' } });
}

beforeEach(() => Object.values(s).forEach((f) => f.mockClear()));

describe('ShellLayout chrome (the app-wide layout)', () => {
    it('renders the user, storage meter and slot body', () => {
        const wrapper = mountShell();
        expect(wrapper.text()).toContain('Ada Lovelace');
        expect(wrapper.text()).toContain('page body');
        expect(wrapper.text()).toContain('5.0 MB of 10.0 MB');
    });

    it('runs a global cross-content search on Enter', async () => {
        const wrapper = mountShell();
        const input = wrapper.find('#global-search');
        await input.setValue('report');
        await input.trigger('keyup', { key: 'Enter' });
        // Navbar search routes to the cross-content SearchController (/search?q=).
        expect(s.get).toHaveBeenCalledWith('/search', { q: 'report' });
    });

    it('re-runs the search when the scope dropdown is used', async () => {
        const wrapper = mountShell();
        await wrapper.find('#global-search').setValue('report');
        const thisFolder = wrapper.findAll('button.dd-item').find((b) => b.text().includes('This folder'));
        await thisFolder!.trigger('click');
        expect(s.get).toHaveBeenCalledWith('/search', { q: 'report' });
    });

    it('reads the active query from the URL', () => {
        const wrapper = mountShell({ url: '/search?q=hello' });
        expect((wrapper.find('#global-search').element as HTMLInputElement).value).toBe('hello');
    });

    it('toggles the color mode from the user menu', async () => {
        const wrapper = mountShell();
        const theme = wrapper.findAll('button.dd-item').find((b) => b.text().includes('Theme:'));
        await theme!.trigger('click');
        expect(s.toggleColorMode).toHaveBeenCalled();
    });

    it('logs out from the user menu', async () => {
        const wrapper = mountShell();
        const logout = wrapper.findAll('button.dd-item').find((b) => b.text().includes('Logout'));
        await logout!.trigger('click');
        expect(s.post).toHaveBeenCalledWith('/logout');
    });
});

describe('GuestLayout', () => {
    it('renders branding and the slot', () => {
        const wrapper = mount(GuestLayout, { props: { title: 'Sign in' }, slots: { default: '<form>hello</form>' } });
        expect(wrapper.text()).toContain('Silo');
        expect(wrapper.text()).toContain('hello');
    });
});
