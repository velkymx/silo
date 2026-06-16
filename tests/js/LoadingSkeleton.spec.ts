import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import LoadingSkeleton from '@/Components/LoadingSkeleton.vue';

describe('LoadingSkeleton', () => {
    it('renders the requested number of placeholder rows', () => {
        const wrapper = mount(LoadingSkeleton, { props: { rows: 3, cols: 2 } });
        expect(wrapper.findAll('.border-bottom')).toHaveLength(3);
        expect(wrapper.find('[aria-busy="true"]').exists()).toBe(true);
        expect(wrapper.find('.visually-hidden').text()).toBe('Loading…');
    });

    it('defaults to 6 rows', () => {
        const wrapper = mount(LoadingSkeleton);
        expect(wrapper.findAll('.border-bottom')).toHaveLength(6);
    });
});
