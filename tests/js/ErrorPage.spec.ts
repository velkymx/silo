import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';

const { routerVisit } = vi.hoisted(() => ({ routerVisit: vi.fn() }));
vi.mock('@inertiajs/vue3', () => ({
    router: { visit: routerVisit },
    Head: { name: 'Head', template: '<span><slot /></span>' },
}));

import Error from '@/Pages/Errors/Error.vue';

describe('Errors/Error page', () => {
    beforeEach(() => routerVisit.mockClear());

    it('renders known status copy (404)', () => {
        const wrapper = mount(Error, { props: { status: 404 } });
        expect(wrapper.text()).toContain('404');
        expect(wrapper.text()).toContain('doesn’t exist');
    });

    it('falls back to generic copy for unknown status', () => {
        const wrapper = mount(Error, { props: { status: 418 } });
        expect(wrapper.text()).toContain('An unexpected error occurred.');
    });

    it('Home button visits /', async () => {
        const wrapper = mount(Error, { props: { status: 500 } });
        const home = wrapper.findAll('button').find((b) => b.text().includes('Home'));
        await home!.trigger('click');
        expect(routerVisit).toHaveBeenCalledWith('/');
    });

    it('Go back visits / when there is no history', async () => {
        Object.defineProperty(window.history, 'length', { value: 1, configurable: true });
        const wrapper = mount(Error, { props: { status: 403 } });
        const back = wrapper.findAll('button').find((b) => b.text().includes('Go back'));
        await back!.trigger('click');
        expect(routerVisit).toHaveBeenCalledWith('/');
    });
});
