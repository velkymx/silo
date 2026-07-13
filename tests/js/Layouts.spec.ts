import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { ref } from 'vue';

const s = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), del: vi.fn(), visit: vi.fn(), toggleColorMode: vi.fn(), paletteOpen: vi.fn() }));
vi.mock('@/composables/useCommandPalette', () => ({
    useCommandPalette: () => ({ state: { open: false }, open: s.paletteOpen, close: vi.fn(), toggle: vi.fn() }),
}));
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

    it('links to the About page from the footer', () => {
        const wrapper = mountShell();
        expect(wrapper.find('[data-testid="about-link"]').exists()).toBe(true);
    });

    it('opens the command palette when the search trigger is clicked', async () => {
        const wrapper = mountShell();
        await wrapper.find('#global-search').trigger('click');
        // The navbar search is a trigger — it opens the palette, never navigates.
        expect(s.paletteOpen).toHaveBeenCalled();
        expect(s.get).not.toHaveBeenCalled();
    });

    it('toggles the color mode from the user menu', async () => {
        const wrapper = mountShell();
        const theme = wrapper.findAll('button.dd-item').find((b) => b.text().includes('Theme:'));
        await theme!.trigger('click');
        expect(s.toggleColorMode).toHaveBeenCalled();
    });

    it('opens my wall and settings from the user menu', async () => {
        const wrapper = mountShell();
        const wall = wrapper.findAll('button.dd-item').find((b) => b.text().includes('My wall'));
        await wall!.trigger('click');
        expect(s.visit).toHaveBeenCalledWith('/directory/1');

        const settings = wrapper.findAll('button.dd-item').find((b) => b.text().includes('Settings'));
        await settings!.trigger('click');
        expect(s.visit).toHaveBeenCalledWith('/profile');
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
