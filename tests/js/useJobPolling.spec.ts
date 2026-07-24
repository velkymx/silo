import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { defineComponent, ref, h, type Ref } from 'vue';
import { mount } from '@vue/test-utils';
import { useJobPolling, type Processable } from '@/composables/useJobPolling';

function harness(files: Ref<Processable[]>, onTick: () => void) {
    const Comp = defineComponent({
        setup() {
            const api = useJobPolling(files, onTick, 1000);
            return { api };
        },
        render() {
            return h('div');
        },
    });
    return mount(Comp);
}

describe('useJobPolling', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    it('ticks while pending, stops when done', () => {
        const files = ref<Processable[]>([{ status: 'pending' }]);
        const onTick = vi.fn();
        const wrapper = harness(files, onTick);
        wrapper.vm.api.start();

        // immediate tick on start + interval ticks at 1000ms and 2000ms = 3 total in 2500ms
        vi.advanceTimersByTime(2500);
        expect(onTick).toHaveBeenCalledTimes(3);

        files.value = [{ status: 'ready' }];
        vi.advanceTimersByTime(1000); // next tick sees no pending → stops
        const after = onTick.mock.calls.length;
        vi.advanceTimersByTime(3000);
        expect(onTick.mock.calls.length).toBe(after); // no further ticks
    });

    it('does not start when nothing is pending', () => {
        const files = ref<Processable[]>([{ status: 'ready' }]);
        const onTick = vi.fn();
        const wrapper = harness(files, onTick);
        wrapper.vm.api.start();
        vi.advanceTimersByTime(5000);
        expect(onTick).not.toHaveBeenCalled();
    });

    it('auto-starts when a pending item appears', async () => {
        const files = ref<Processable[]>([{ status: 'ready' }]);
        const onTick = vi.fn();
        const wrapper = harness(files, onTick);

        files.value = [{ status: 'pending' }];
        await wrapper.vm.$nextTick();
        vi.advanceTimersByTime(1000);
        expect(onTick).toHaveBeenCalled();
    });

    it('cleans up the timer on unmount', () => {
        const files = ref<Processable[]>([{ status: 'pending' }]);
        const onTick = vi.fn();
        const wrapper = harness(files, onTick);
        wrapper.vm.api.start(); // triggers one immediate tick
        const callsBeforeUnmount = onTick.mock.calls.length;
        wrapper.unmount();
        vi.advanceTimersByTime(5000);
        expect(onTick.mock.calls.length).toBe(callsBeforeUnmount); // no further ticks after unmount
    });
});
