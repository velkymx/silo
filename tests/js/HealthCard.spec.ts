import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';

import HealthCard, { type SystemHealth } from '@/Components/Dashboard/HealthCard.vue';

const attention: SystemHealth = {
    attentionCount: 2,
    attention: [
        { category: 'security', label: 'Vault key', status: 'warn', detail: 'Falling back to APP_KEY.' },
        { category: 'runtime', label: 'Silo update', status: 'red', detail: 'An update is available (v2.0.0 -> v2.3.0).' },
    ],
    facts: ['Silo v2.0.0', 'Database reachable', 'Queue running'],
};

const healthy: SystemHealth = { attentionCount: 0, attention: [], facts: ['Silo v2.0.0', 'Database reachable', 'Queue running'] };

describe('HealthCard', () => {
    it('lists the attention items with an educational detail', () => {
        const wrapper = mount(HealthCard, { props: { systemHealth: attention } });
        expect(wrapper.text()).toContain('2 items need attention');
        expect(wrapper.text()).toContain('Vault key');
        expect(wrapper.text()).toContain('Falling back to APP_KEY.');
        expect(wrapper.findAll('li')).toHaveLength(2);
    });

    it('shows the healthy summary with three facts and no list', () => {
        const wrapper = mount(HealthCard, { props: { systemHealth: healthy } });
        expect(wrapper.text()).toContain('Everything looks healthy.');
        expect(wrapper.text()).toContain('Silo v2.0.0 · Database reachable · Queue running');
        expect(wrapper.findAll('li')).toHaveLength(0);
    });

    it('uses the singular for a single attention item', () => {
        const one: SystemHealth = { ...attention, attentionCount: 1, attention: [attention.attention[0]] };
        const wrapper = mount(HealthCard, { props: { systemHealth: one } });
        expect(wrapper.text()).toContain('1 item needs attention');
    });
});
