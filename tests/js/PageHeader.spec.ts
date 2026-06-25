import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import PageHeader from '@/Components/PageHeader.vue';

describe('PageHeader', () => {
    it('renders a title heading', () => {
        const wrapper = mount(PageHeader, { props: { title: 'Vault', icon: 'lock-fill' } });
        const h1 = wrapper.get('h1');
        expect(h1.text()).toContain('Vault');
    });

    it('renders a breadcrumb instead of a title when provided', () => {
        const wrapper = mount(PageHeader, {
            props: { title: 'Files', breadcrumbs: [{ text: 'Home', href: '/' }, { text: 'Docs', active: true }] },
        });
        // Breadcrumb mode suppresses the title heading.
        expect(wrapper.find('h1').exists()).toBe(false);
        const bc = wrapper.findComponent({ name: 'VibeBreadcrumb' });
        expect(bc.exists()).toBe(true);
    });

    it('renders the actions slot', () => {
        const wrapper = mount(PageHeader, {
            props: { title: 'Vault' },
            slots: { actions: '<button class="act">Add</button>' },
        });
        expect(wrapper.find('button.act').exists()).toBe(true);
    });
});
