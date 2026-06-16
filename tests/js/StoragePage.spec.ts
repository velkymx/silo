import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';

vi.mock('@inertiajs/vue3', () => ({
    router: { get: vi.fn(), on: vi.fn(() => () => {}) },
    usePage: () => ({ props: { flash: {}, errors: {} } }),
}));

import Storage from '@/Pages/Storage/Index.vue';

describe('Storage/Index page', () => {
    const props = {
        summary: { used: 5 * 1024 * 1024, quota: 10 * 1024 * 1024 },
        nodes: [{ id: 1, name: 'Docs', parent_id: null, is_dir: true, size: 4 * 1024 * 1024 }],
        largest: [{ id: 2, name: 'big.bin', size: 3 * 1024 * 1024, category: 'archive' }],
        byCategory: { image: 2 * 1024 * 1024, archive: 3 * 1024 * 1024 },
    };

    it('renders the usage summary and percentage', () => {
        const wrapper = mount(Storage, { props });
        expect(wrapper.text()).toContain('5.0 MB');
        expect(wrapper.text()).toContain('(50%)');
    });

    it('lists categories and largest files', () => {
        const wrapper = mount(Storage, { props });
        expect(wrapper.text()).toContain('archive');
        expect(wrapper.text()).toContain('big.bin');
    });

    it('shows an empty treemap message when there is nothing to lay out', () => {
        // jsdom reports a 0-width box, so no tiles are produced.
        const wrapper = mount(Storage, { props: { ...props, nodes: [] } });
        expect(wrapper.text()).toContain('Nothing stored yet.');
    });
});
