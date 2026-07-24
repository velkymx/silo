import { describe, it, expect, beforeEach, vi } from 'vitest';

const hoisted = vi.hoisted(() => {
    const listener = { fn: null };
    return {
        listener,
        // Vue component stubs — we only test the composable here, not the
        // template, so the stubs are minimal.
    };
});

const addSpy = vi.spyOn(document, 'addEventListener');
const removeSpy = vi.spyOn(document, 'removeEventListener');

import { useCommandPalette } from '@/composables/useCommandPalette';

describe('useCommandPalette (FE-P1-32)', () => {
    beforeEach(() => {
        const p = useCommandPalette();
        p.close();
    });

    it('starts closed', () => {
        const p = useCommandPalette();
        expect(p.state.open).toBe(false);
    });

    it('open() flips state to true', () => {
        const p = useCommandPalette();
        p.open();
        expect(p.state.open).toBe(true);
        p.close();
    });

    it('toggle() flips state both ways', () => {
        const p = useCommandPalette();
        p.toggle();
        expect(p.state.open).toBe(true);
        p.toggle();
        expect(p.state.open).toBe(false);
    });

    it('shares state across consumers (singleton)', () => {
        const a = useCommandPalette();
        const b = useCommandPalette();
        a.open();
        expect(b.state.open).toBe(true);
        b.close();
    });
});
