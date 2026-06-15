import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import EmptyState from '@/Components/EmptyState.vue';

describe('EmptyState', () => {
    it('renders title and hint', () => {
        const wrapper = mount(EmptyState, { props: { title: 'Nothing here', hint: 'Add something' } });
        expect(wrapper.text()).toContain('Nothing here');
        expect(wrapper.text()).toContain('Add something');
        expect(wrapper.find('i.bi').exists()).toBe(true);
    });

    it('omits the hint when not provided', () => {
        const wrapper = mount(EmptyState, { props: { title: 'Empty' } });
        expect(wrapper.text()).toContain('Empty');
        expect(wrapper.find('.small').exists()).toBe(false);
    });
});
