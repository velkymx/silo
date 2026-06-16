import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { ref } from 'vue';

const s = vi.hoisted(() => ({ get: vi.fn(), post: vi.fn(), del: vi.fn(), visit: vi.fn(), toggleColorMode: vi.fn() }));
const page = { url: '/', props: {} as Record<string, unknown> };
vi.mock('@inertiajs/vue3', () => ({
    router: { get: s.get, post: s.post, delete: s.del, visit: s.visit },
    usePage: () => page,
}));
vi.mock('@velkymx/vibeui', () => ({
    useColorMode: () => ({ colorMode: ref('auto'), toggleColorMode: s.toggleColorMode }),
    useBreakpoints: () => ({ isMobile: ref(false) }),
}));

import AppLayout from '@/Layouts/AppLayout.vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { useDialogHost } from '@/composables/useConfirm';

function mountApp(props: Record<string, unknown> = {}) {
    page.url = (props.url as string) ?? '/';
    page.props = {
        auth: { user: { id: 1, name: 'Ada Lovelace', is_admin: true } },
        flash: {},
        storage: { used: 5 * 1024 * 1024, quota: 10 * 1024 * 1024 },
        savedSearches: [{ id: 7, name: 'Big PDFs', params: { ftype: 'pdf' } }],
        currentFolder: 12,
        ...props,
    };
    return mount(AppLayout, { slots: { default: '<p>page body</p>' } });
}

beforeEach(() => Object.values(s).forEach((f) => f.mockClear()));

describe('AppLayout', () => {
    it('renders the user, storage meter and slot body', () => {
        const wrapper = mountApp();
        expect(wrapper.text()).toContain('Ada Lovelace');
        expect(wrapper.text()).toContain('page body');
        expect(wrapper.text()).toContain('Big PDFs');
        expect(wrapper.text()).toContain('5.0 MB of 10.0 MB');
    });

    it('runs a global search on Enter', async () => {
        const wrapper = mountApp();
        const input = wrapper.find('#global-search');
        await input.setValue('report');
        await input.trigger('keyup', { key: 'Enter' });
        expect(s.get).toHaveBeenCalledWith('/', { search: 'report' });
    });

    it('scopes the search to the current folder', async () => {
        const wrapper = mountApp();
        await wrapper.find('#global-search').setValue('report');
        // The scope dropdown emits item-click; "This folder" sets scope + re-runs.
        const thisFolder = wrapper.findAll('button.dd-item').find((b) => b.text().includes('This folder'));
        await thisFolder!.trigger('click');
        expect(s.get).toHaveBeenCalledWith('/', { search: 'report', scope: 'folder', folder: 12 });
    });

    it('reads the active search + scope from the URL', () => {
        const wrapper = mountApp({ url: '/?search=hello&scope=folder' });
        expect((wrapper.find('#global-search').element as HTMLInputElement).value).toBe('hello');
    });

    it('toggles the sidebar', async () => {
        const wrapper = mountApp();
        expect(wrapper.find('aside').exists()).toBe(true);
        const toggle = wrapper.findAll('button').find((b) => b.attributes('title') === 'Toggle sidebar');
        await toggle!.trigger('click');
        expect(wrapper.find('aside').exists()).toBe(false);
    });

    it('toggles the color mode', async () => {
        const wrapper = mountApp();
        const theme = wrapper.findAll('button').find((b) => b.attributes('title')?.startsWith('Theme:'));
        await theme!.trigger('click');
        expect(s.toggleColorMode).toHaveBeenCalled();
    });

    it('logs out from the user menu', async () => {
        const wrapper = mountApp();
        const logout = wrapper.findAll('button.dd-item').find((b) => b.text().includes('Logout'));
        await logout!.trigger('click');
        expect(s.post).toHaveBeenCalledWith('/logout');
    });

    it('runs a saved search', async () => {
        const wrapper = mountApp();
        await wrapper.find('.saved-search').trigger('click');
        expect(s.get).toHaveBeenCalledWith('/', { ftype: 'pdf' });
    });

    it('deletes a saved search after confirming', async () => {
        const wrapper = mountApp();
        await wrapper.find('.saved-search .del-btn').trigger('click');
        const host = useDialogHost();
        expect(host.state.open).toBe(true);
        host.accept();
        await flushPromises();
        expect(s.del).toHaveBeenCalledWith('/saved-searches/7', expect.anything());
    });

    it('hides admin nav for non-admins', () => {
        const wrapper = mountApp({ auth: { user: { id: 2, name: 'Reg User', is_admin: false } } });
        expect(wrapper.text()).not.toContain('Audit');
    });
});

describe('GuestLayout', () => {
    it('renders branding and the slot', () => {
        const wrapper = mount(GuestLayout, { props: { title: 'Sign in' }, slots: { default: '<form>hello</form>' } });
        expect(wrapper.text()).toContain('File Manager');
        expect(wrapper.text()).toContain('hello');
    });
});
