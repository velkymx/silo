import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import SectionRail from '@/Components/SectionRail.vue';

const sections = [
    { key: 'all', icon: 'folder', label: 'All' },
    { key: 'recent', icon: 'clock-history', label: 'Recent' },
    { key: 'trash', icon: 'trash', label: 'Trash' },
];

describe('SectionRail', () => {
    it('renders one control per section', () => {
        const wrapper = mount(SectionRail, { props: { sections, active: 'all' } });
        expect(wrapper.findAll('[data-section]')).toHaveLength(3);
    });

    it('marks the active section with aria-current', () => {
        const wrapper = mount(SectionRail, { props: { sections, active: 'recent' } });
        const active = wrapper.get('[data-section="recent"]');
        expect(active.attributes('aria-current')).toBe('true');
        expect(wrapper.get('[data-section="all"]').attributes('aria-current')).toBeUndefined();
    });

    it('emits select-section with the section key on click', async () => {
        const wrapper = mount(SectionRail, { props: { sections, active: 'all' } });
        await wrapper.get('[data-section="trash"]').trigger('click');
        expect(wrapper.emitted('select-section')).toEqual([['trash']]);
    });

    it('exposes each section label for accessibility', () => {
        const wrapper = mount(SectionRail, { props: { sections, active: 'all' } });
        expect(wrapper.get('[data-section="recent"]').attributes('aria-label')).toBe('Recent');
    });
});
