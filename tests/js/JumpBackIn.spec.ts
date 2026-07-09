import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { mount } from '@vue/test-utils';

vi.mock('@inertiajs/vue3', () => ({
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

import JumpBackIn, { type JumpBackInItem } from '@/Components/Dashboard/JumpBackIn.vue';

const item: JumpBackInItem = {
    id: 7,
    title: 'Q3 Planning',
    type: 'note',
    url: '/notes?open=7',
    editedAt: new Date().toISOString(),
};

describe('JumpBackIn', () => {
    beforeEach(() => vi.useFakeTimers().setSystemTime(new Date('2026-07-08T12:00:00Z')));
    afterEach(() => vi.useRealTimers());

    it('renders nothing when there is no item', () => {
        const wrapper = mount(JumpBackIn, { props: { item: null } });
        expect(wrapper.find('[data-testid="jump-back-in"]').exists()).toBe(false);
    });

    it('links to the item url and shows its title', () => {
        const wrapper = mount(JumpBackIn, { props: { item } });
        const link = wrapper.find('[data-testid="jump-back-in"]');
        expect(link.exists()).toBe(true);
        expect(link.attributes('href')).toBe('/notes?open=7');
        expect(wrapper.text()).toContain('Q3 Planning');
        expect(wrapper.text()).toContain('Jump back in');
    });

    it('formats the edited time relative to now', () => {
        const edited = { ...item, editedAt: new Date('2026-07-08T11:17:00Z').toISOString() };
        const wrapper = mount(JumpBackIn, { props: { item: edited } });
        expect(wrapper.text()).toContain('Edited 43 min ago');
    });

    it('shows "just now" for a fresh edit', () => {
        const edited = { ...item, editedAt: new Date('2026-07-08T11:59:40Z').toISOString() };
        const wrapper = mount(JumpBackIn, { props: { item: edited } });
        expect(wrapper.text()).toContain('Edited just now');
    });
});
