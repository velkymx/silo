import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { ref } from 'vue';

const page = { url: '/', props: {} as Record<string, unknown> };
vi.mock('@inertiajs/vue3', () => ({
    router: { get: vi.fn(), post: vi.fn(), delete: vi.fn(), visit: vi.fn() },
    usePage: () => page,
    Link: { name: 'Link', template: '<a><slot /></a>' },
}));
vi.mock('@velkymx/vibeui', () => ({
    useColorMode: () => ({ colorMode: ref('auto'), toggleColorMode: vi.fn() }),
    useBreakpoints: () => ({ isMobile: ref(false) }),
}));

import ShellLayout from '@/Layouts/ShellLayout.vue';

function mountShell() {
    page.url = '/';
    page.props = { auth: { user: { id: 1, name: 'Ada Lovelace', is_admin: false } }, flash: {} };
    return mount(ShellLayout, {
        slots: {
            viewNav: '<div class="vn">VN</div>',
            contents: '<div class="ct">CT</div>',
            detail: '<div class="dt">DT</div>',
        },
    });
}

describe('ShellLayout', () => {
    it('renders GlobalRail in the rail pane', () => {
        const wrapper = mountShell();
        expect(wrapper.findComponent({ name: 'GlobalRail' }).exists()).toBe(true);
    });

    it('renders viewNav slot content in the folders pane', () => {
        const wrapper = mountShell();
        const pane = wrapper.get('[data-pane="folders"]');
        expect(pane.find('.vn').exists()).toBe(true);
    });

    it('renders contents slot content (wrapped in #main-content) in the contents pane', () => {
        const wrapper = mountShell();
        const pane = wrapper.get('[data-pane="contents"]');
        expect(pane.find('.ct').exists()).toBe(true);
        expect(pane.find('#main-content').exists()).toBe(true);
    });

    it('renders detail slot content in the detail pane', () => {
        const wrapper = mountShell();
        const pane = wrapper.get('[data-pane="detail"]');
        expect(pane.find('.dt').exists()).toBe(true);
    });
});
