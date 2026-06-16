import { ref, type Ref } from 'vue';

interface BusyGuardOptions {
    /**
     * When true (default) the guard releases itself after the action returns —
     * fine for synchronous work. For async router calls pass `false` and call
     * `release()` from the request's `onFinish` so the button stays disabled
     * for the whole round-trip.
     */
    autoRelease?: boolean;
}

interface BusyGuard {
    busy: Ref<boolean>;
    run: (action: () => void) => void;
    release: () => void;
}

/**
 * Single-flight guard for buttons that mutate server state. While `busy` is
 * true, further `run()` calls are dropped — defeating double-click / rapid
 * re-submit. Bind `:disabled="busy"` for the visual cue.
 */
export function useBusyGuard(options: BusyGuardOptions = {}): BusyGuard {
    const autoRelease = options.autoRelease ?? true;
    const busy = ref(false);

    function release(): void {
        busy.value = false;
    }

    function run(action: () => void): void {
        if (busy.value) return;
        busy.value = true;
        try {
            action();
        } finally {
            if (autoRelease) release();
        }
    }

    return { busy, run, release };
}
