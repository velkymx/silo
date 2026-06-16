import { describe, it, expect, vi } from 'vitest';
import { useBusyGuard } from '@/composables/useBusyGuard';

describe('useBusyGuard', () => {
    it('runs the action and flips busy around it', () => {
        const seen: boolean[] = [];
        const { busy, run } = useBusyGuard();
        run(() => seen.push(busy.value));
        expect(seen).toEqual([true]);   // busy was true while running
        expect(busy.value).toBe(false); // released for a synchronous action
    });

    it('blocks re-entry while busy (one network call on double-click)', () => {
        const fn = vi.fn(() => { /* never finishes; caller releases via onFinish */ });
        const { busy, run } = useBusyGuard({ autoRelease: false });
        run(fn);
        run(fn); // second click while busy
        run(fn);
        expect(fn).toHaveBeenCalledOnce();
        expect(busy.value).toBe(true);
    });

    it('release() lets the next call through', () => {
        const fn = vi.fn();
        const { busy, run, release } = useBusyGuard({ autoRelease: false });
        run(fn);
        release();
        expect(busy.value).toBe(false);
        run(fn);
        expect(fn).toHaveBeenCalledTimes(2);
    });
});
