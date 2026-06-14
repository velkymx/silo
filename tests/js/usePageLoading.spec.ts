import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';
import { defineComponent, h } from 'vue';

const handlers: Record<string, (e?: unknown) => void> = {};
const off = vi.fn();
vi.mock('@inertiajs/vue3', () => ({
    router: {
        on: vi.fn((event: string, cb: (e?: unknown) => void) => { handlers[event] = cb; return off; }),
    },
}));

import { usePageLoading } from '@/composables/usePageLoading';

const Harness = defineComponent({
    setup() {
        const { loading } = usePageLoading();
        return () => h('div', loading.value ? 'busy' : 'idle');
    },
});

describe('usePageLoading', () => {
    beforeEach(() => { off.mockClear(); for (const k of Object.keys(handlers)) delete handlers[k]; });

    it('is idle on mount and busy between start and finish', async () => {
        const wrapper = mount(Harness);
        expect(wrapper.text()).toBe('idle');

        handlers.start?.();
        await wrapper.vm.$nextTick();
        expect(wrapper.text()).toBe('busy');

        handlers.finish?.();
        await wrapper.vm.$nextTick();
        expect(wrapper.text()).toBe('idle');
    });

    it('ignores partial (only:) reloads so polling does not flash a skeleton', async () => {
        const wrapper = mount(Harness);
        handlers.start?.({ detail: { visit: { only: ['files'] } } });
        await wrapper.vm.$nextTick();
        expect(wrapper.text()).toBe('idle');
    });

    it('unsubscribes its listeners on unmount', () => {
        const wrapper = mount(Harness);
        wrapper.unmount();
        expect(off).toHaveBeenCalled();
    });
});
