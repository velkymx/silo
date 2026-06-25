import { computed, watch, onBeforeUnmount, type Ref } from 'vue';
import { FileStatus } from '../lib/constants';

export interface Processable {
    status?: string;
}

/**
 * Polls while any item is still being processed (status === 'pending'),
 * calling `onTick` on each interval and stopping automatically when none
 * remain. Restarts when new pending items appear; cleans up on unmount.
 */
export function useJobPolling(items: Ref<Processable[]>, onTick: () => void, interval = 3000) {
    const hasPending = computed(() => items.value.some((f) => f.status === FileStatus.PENDING));
    let timer: ReturnType<typeof setInterval> | null = null;

    function stop(): void {
        if (timer) {
            clearInterval(timer);
            timer = null;
        }
    }

    function start(): void {
        if (timer || !hasPending.value) return;
        timer = setInterval(() => {
            if (!hasPending.value) {
                stop();
                return;
            }
            onTick();
        }, interval);
    }

    watch(hasPending, (pending) => {
        if (pending) start();
    });

    onBeforeUnmount(stop);

    return { hasPending, start, stop };
}
