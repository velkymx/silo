import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';

vi.mock('@inertiajs/vue3', () => ({
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

import WhatsNewCard, { type WhatsNew } from '@/Components/Dashboard/WhatsNewCard.vue';

const whatsNew: WhatsNew = {
    unreadCount: 12,
    articles: [{ id: 1, title: 'Laravel 13 released', feed: 'Laravel News', url: '/rss/items/1' }],
    inboxUrl: '/rss',
};

describe('WhatsNewCard', () => {
    it('renders nothing when there is no unread data', () => {
        const wrapper = mount(WhatsNewCard, { props: { whatsNew: null } });
        expect(wrapper.find('.whats-new').exists()).toBe(false);
    });

    it('is a single compact strip: count, newest headline, inbox link', () => {
        const wrapper = mount(WhatsNewCard, { props: { whatsNew } });
        const strip = wrapper.find('[data-testid="whats-new-strip"]');
        expect(strip.exists()).toBe(true);
        expect(strip.attributes('href')).toBe('/rss');
        expect(wrapper.text()).toContain('12 unread articles');
        expect(wrapper.text()).toContain('Laravel 13 released');
        expect(wrapper.text()).toContain('View Inbox');
        // No article list — the home screen is not a feed.
        expect(wrapper.findAll('li')).toHaveLength(0);
    });

    it('uses the singular for a single unread article', () => {
        const wrapper = mount(WhatsNewCard, { props: { whatsNew: { ...whatsNew, unreadCount: 1 } } });
        expect(wrapper.text()).toContain('1 unread article');
        expect(wrapper.text()).not.toContain('1 unread articles');
    });
});
