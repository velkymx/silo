import { describe, it, expect } from 'vitest';
import { seedForDate, deterministicId } from '@/lib/sodoku/seed';

describe('sodoku/seed — seedForDate', () => {
    it('returns the same seed for the same date and difficulty', () => {
        const s1 = seedForDate('2026-06-24', 'beginner');
        const s2 = seedForDate('2026-06-24', 'beginner');
        expect(s1).toBe(s2);
    });

    it('returns different seeds for different dates', () => {
        const s1 = seedForDate('2026-06-24', 'beginner');
        const s2 = seedForDate('2026-06-25', 'beginner');
        expect(s1).not.toBe(s2);
    });

    it('returns different seeds for different difficulties on the same date', () => {
        const s1 = seedForDate('2026-06-24', 'beginner');
        const s2 = seedForDate('2026-06-24', 'intermediate');
        expect(s1).not.toBe(s2);
    });

    it('returns a positive integer', () => {
        const s = seedForDate('2026-06-24', 'beginner');
        expect(Number.isInteger(s)).toBe(true);
        expect(s).toBeGreaterThan(0);
    });

    it('returns deterministic results for the same input', () => {
        // Run 100 times to confirm determinism.
        for (let i = 0; i < 100; i++) {
            expect(seedForDate('2026-06-24', 'beginner')).toBe(seedForDate('2026-06-24', 'beginner'));
        }
    });
});

describe('sodoku/seed — deterministicId', () => {
    it('returns a stable string id from a numeric seed', () => {
        const id1 = deterministicId(12345);
        const id2 = deterministicId(12345);
        expect(id1).toBe(id2);
    });

    it('returns different ids for different seeds', () => {
        const id1 = deterministicId(12345);
        const id2 = deterministicId(67890);
        expect(id1).not.toBe(id2);
    });

    it('returns a string of reasonable length', () => {
        const id = deterministicId(42);
        expect(typeof id).toBe('string');
        expect(id.length).toBeGreaterThanOrEqual(8);
        expect(id.length).toBeLessThanOrEqual(64);
    });
});
