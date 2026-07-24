import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';

vi.mock('@inertiajs/vue3', () => ({
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

import AttentionCard, { type AttentionItem } from '@/Components/Dashboard/AttentionCard.vue';

const items: AttentionItem[] = [
    { tier: 'red', title: '2 files failed a virus scan', url: '/' },
    { tier: 'yellow', title: 'Storage is 91% full', url: '/usage' },
    { tier: 'blue', title: '1 feed has not updated in over a week', url: '/rss' },
];

describe('AttentionCard', () => {
    it('shows a quiet all-clear when empty', () => {
        const wrapper = mount(AttentionCard, { props: { items: [] } });
        expect(wrapper.find('[data-testid="all-clear"]').exists()).toBe(true);
        expect(wrapper.find('.list-group').exists()).toBe(false);
    });

    it('renders a tiered, linked row per item', () => {
        const wrapper = mount(AttentionCard, { props: { items } });
        const rows = wrapper.findAll('a.list-group-item');
        expect(rows).toHaveLength(3);
        expect(rows[0].attributes('data-tier')).toBe('red');
        expect(rows[0].attributes('href')).toBe('/');
        expect(rows[1].attributes('data-tier')).toBe('yellow');
        expect(wrapper.text()).toContain('Storage is 91% full');
        expect(wrapper.find('[data-testid="all-clear"]').exists()).toBe(false);
    });
});
