import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';

const page = { url: '/', props: {} as Record<string, unknown> };
vi.mock('@inertiajs/vue3', () => ({
    usePage: () => page,
    Link: { name: 'Link', template: '<a><slot /></a>' },
}));

import GlobalRail from '@/Components/GlobalRail.vue';

function mountRail(opts: { url?: string; isAdmin?: boolean } = {}) {
    page.url = opts.url ?? '/';
    page.props = { auth: { user: { id: 1, name: 'Ada Lovelace', is_admin: opts.isAdmin ?? false } } };
    return mount(GlobalRail);
}

describe('GlobalRail', () => {
    it('renders one entry per destination', () => {
        const wrapper = mountRail();
        ['dashboard', 'home', 'notes', 'bookmarks', 'photos', 'trash'].forEach((key) => {
            expect(wrapper.find(`[data-nav="${key}"]`).exists()).toBe(true);
        });
    });

    it('marks the Home (dashboard) entry active on /dashboard', () => {
        const wrapper = mountRail({ url: '/dashboard' });
        expect(wrapper.get('[data-nav="dashboard"]').attributes('aria-current')).toBe('page');
        expect(wrapper.get('[data-nav="home"]').attributes('aria-current')).toBeUndefined();
    });

    it('marks the entry matching the current path as active', () => {
        const wrapper = mountRail({ url: '/notes' });
        expect(wrapper.get('[data-nav="notes"]').attributes('aria-current')).toBe('page');
        expect(wrapper.get('[data-nav="home"]').attributes('aria-current')).toBeUndefined();
    });

    it('hides admin entries for non-admins', () => {
        const wrapper = mountRail({ isAdmin: false });
        expect(wrapper.find('[data-nav="users"]').exists()).toBe(false);
    });

    it('shows admin entries for admins', () => {
        const wrapper = mountRail({ isAdmin: true });
        expect(wrapper.find('[data-nav="users"]').exists()).toBe(true);
    });
});
