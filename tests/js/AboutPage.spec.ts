import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';

const page = { url: '/about', props: {} as Record<string, unknown> };
vi.mock('@inertiajs/vue3', () => ({
    router: { get: vi.fn(), post: vi.fn(), visit: vi.fn() },
    usePage: () => page,
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
}));
vi.mock('@velkymx/vibeui', () => ({
    useColorMode: () => ({ colorMode: { value: 'auto' }, toggleColorMode: vi.fn() }),
    useBreakpoints: () => ({ isMobile: { value: false } }),
}));

import About from '@/Pages/About.vue';

const props = {
    version: '2.1.0',
    developer: {
        name: 'Alan Bollinger',
        title: 'Technology consultant',
        hire_url: 'https://blog.ajb.bz/hire-alan-bollinger/',
        linkedin: 'https://www.linkedin.com/in/abollinger',
    },
};

function mountAbout() {
    page.props = {
        auth: { user: { id: 1, name: 'Ada' } },
        flash: {},
        storage: { used: 0, quota: 0 },
    };
    return mount(About, { props });
}

describe('About page', () => {
    it('shows the version and developer identity', () => {
        const wrapper = mountAbout();
        expect(wrapper.find('[data-testid="version"]').text()).toBe('v2.1.0');
        expect(wrapper.text()).toContain('Built by Alan Bollinger');
    });

    it('links out to the hire page and LinkedIn', () => {
        const wrapper = mountAbout();
        expect(wrapper.find('[data-testid="hire-link"]').attributes('href')).toBe('https://blog.ajb.bz/hire-alan-bollinger/');
        expect(wrapper.find('[data-testid="linkedin-link"]').attributes('href')).toBe('https://www.linkedin.com/in/abollinger');
    });
});
