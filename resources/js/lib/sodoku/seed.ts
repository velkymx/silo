/**
 * Deterministic seeding for the daily-puzzle feature.
 *
 * Same date + same difficulty → same seed → same puzzle (the user can
 * share a "today's puzzle" link with a friend and they'll see the same
 * board). Different days → different puzzles.
 *
 * The seed is a small positive integer derived from a simple hash of the
 * ISO date string and the difficulty. We don't need cryptographic strength
 * here — just enough entropy that consecutive days produce visibly
 * different puzzles. FNV-1a (32-bit) is plenty.
 */

export type Difficulty = 'beginner' | 'intermediate' | 'advanced';

const DIFFICULTY_SALT: Record<Difficulty, number> = {
    beginner: 0x4f1e2d3c,
    intermediate: 0x6a7b8c9d,
    advanced: 0x1e2f3a4b,
};

/**
 * FNV-1a 32-bit hash of a string. Stable, fast, no deps, ~5 LoC.
 * Reference: http://www.isthe.com/chongo/tech/comp/fnv/
 */
function fnv1a(s: string): number {
    let h = 0x811c9dc5;
    for (let i = 0; i < s.length; i++) {
        h ^= s.charCodeAt(i);
        h = Math.imul(h, 0x01000193);
    }
    // Force positive 32-bit integer.
    return h >>> 0;
}

/**
 * Deterministic seed for a (date, difficulty) pair. Returns a positive
 * 32-bit integer in the range [1, 2^32). Used to seed a random number
 * generator on the server (or in sudoku-gen) so the same puzzle can be
 * reproduced deterministically.
 */
export function seedForDate(isoDate: string, difficulty: Difficulty): number {
    const h = fnv1a(`${isoDate}:${DIFFICULTY_SALT[difficulty]}`);
    // Ensure non-zero (Math.imul can produce 0 in edge cases).
    return h === 0 ? 1 : h;
}

/**
 * Convert a numeric seed into a stable, URL-safe id string.
 * Used for puzzle URLs: `/break/sodoku/2026-06-24-beginner-{id}`.
 *
 * Pads to 8 characters with leading zeros so all ids are the same length
 * (36^8 ≈ 2.8 trillion — far more than enough to encode a 32-bit seed).
 */
export function deterministicId(seed: number): string {
    return seed.toString(36).padStart(8, '0');
}
