import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { mount } from '@vue/test-utils';

vi.mock('@inertiajs/vue3', () => ({
    Link: { name: 'Link', props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

import ContinueCard, { type ContinueItem } from '@/Components/Dashboard/ContinueCard.vue';

const items: ContinueItem[] = [
    { type: 'note', title: 'Proposal.md', url: '/notes?open=1', at: '2026-07-08T11:17:00Z', reason: 'edited' },
    { type: 'bookmark', title: 'Portal', url: '/bookmarks', at: '2026-07-07T09:00:00Z', reason: 'added' },
    { type: 'article', title: 'Release Notes', url: '/rss/items/5', at: '2026-07-08T11:55:00Z', reason: 'read' },
];

describe('ContinueCard', () => {
    beforeEach(() => vi.useFakeTimers().setSystemTime(new Date('2026-07-08T12:00:00Z')));
    afterEach(() => vi.useRealTimers());

    it('renders nothing when empty', () => {
        const wrapper = mount(ContinueCard, { props: { items: [] } });
        expect(wrapper.find('.continue-card').exists()).toBe(false);
    });

    it('renders a row per item linking to its url', () => {
        const wrapper = mount(ContinueCard, { props: { items } });
        const rows = wrapper.findAll('a.list-group-item');
        expect(rows).toHaveLength(3);
        expect(rows[0].attributes('href')).toBe('/notes?open=1');
        expect(wrapper.text()).toContain('Proposal.md');
    });

    it('composes an object-first subtitle from reason + relative time', () => {
        const wrapper = mount(ContinueCard, { props: { items } });
        expect(wrapper.text()).toContain('Edited 43 min ago');
        expect(wrapper.text()).toContain('Bookmark added yesterday');
        expect(wrapper.text()).toContain('Read 5 min ago');
    });
});
