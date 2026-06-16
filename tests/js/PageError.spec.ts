import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';

const page = { props: { flash: {} as Record<string, unknown>, errors: {} as Record<string, string> } };
vi.mock('@inertiajs/vue3', () => ({ usePage: () => page }));

import PageError from '@/Components/PageError.vue';

describe('PageError', () => {
    it('renders nothing when there is no error', () => {
        page.props.flash = {};
        page.props.errors = {};
        const wrapper = mount(PageError);
        expect(wrapper.find('[data-stub="VibeAlert"]').exists()).toBe(false);
        expect(wrapper.text()).toBe('');
    });

    it('shows the flash error message', () => {
        page.props.flash = { error: 'Something broke' };
        page.props.errors = {};
        const wrapper = mount(PageError);
        expect(wrapper.text()).toContain('Something broke');
    });

    it('joins validation errors', () => {
        page.props.flash = {};
        page.props.errors = { name: 'Name is required', email: 'Email is invalid' };
        const wrapper = mount(PageError);
        expect(wrapper.text()).toContain('Name is required');
        expect(wrapper.text()).toContain('Email is invalid');
    });
});
