import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';

vi.mock('@inertiajs/vue3', () => ({
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

import DailyWordCard from '@/Components/Dashboard/DailyWordCard.vue';

describe('DailyWordCard', () => {
    it('invites while the puzzle is unfinished', () => {
        const wrapper = mount(DailyWordCard, { props: { waiting: true } });
        const card = wrapper.find('[data-testid="daily-word"]');
        expect(card.exists()).toBe(true);
        expect(card.attributes('href')).toBe('/break/dwg');
        expect(wrapper.text()).toContain('Daily Word is waiting for you');
    });

    it('renders nothing once the game is done', () => {
        const wrapper = mount(DailyWordCard, { props: { waiting: false } });
        expect(wrapper.find('[data-testid="daily-word"]').exists()).toBe(false);
    });
});
