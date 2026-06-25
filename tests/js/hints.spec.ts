import { describe, it, expect } from 'vitest';
import { solveBoard, candidatesFor, hint } from '@/lib/sodoku/solver';

const row = (...vals: number[]) => vals;

/**
 * Hint system answers two questions:
 *  1. `candidatesFor(board, r, c)` — for a given empty cell, which digits
 *     could legally be placed there? Used to render the pencil-mark
 *     overlay (tiny grey numbers in the corner of each cell).
 *  2. `hint(board, solution)` — find the most "stuck" empty cell and
 *     reveal the correct digit. Used by the "Hint" button. Returns
 *     `{ row, col, value, technique }` where `technique` is the
 *     human-readable name of the solving method (so the UI can teach the
 *     user).
 */
describe('sodoku/hints — candidatesFor', () => {
    it('returns the set of legal digits for an empty cell', () => {
        // Empty board: every digit is legal for (0, 0).
        const board = Array.from({ length: 9 }, () => Array(9).fill(0));
        expect(candidatesFor(board, 0, 0).sort()).toEqual([1, 2, 3, 4, 5, 6, 7, 8, 9]);
    });

    it('excludes digits already in the row', () => {
        const board = Array.from({ length: 9 }, () => Array(9).fill(0));
        board[0][3] = 5;
        board[0][7] = 9;
        // Row 0 used = {5, 9}.
        expect(candidatesFor(board, 0, 0).sort()).toEqual([1, 2, 3, 4, 6, 7, 8]);
    });

    it('excludes digits already in the column', () => {
        const board = Array.from({ length: 9 }, () => Array(9).fill(0));
        board[3][0] = 5;
        board[7][0] = 9;
        // Col 0 used = {5, 9}.
        expect(candidatesFor(board, 0, 0).sort()).toEqual([1, 2, 3, 4, 6, 7, 8]);
    });

    it('excludes digits already in the 3×3 box', () => {
        const board = Array.from({ length: 9 }, () => Array(9).fill(0));
        board[0][1] = 5;
        board[1][0] = 9;
        board[2][2] = 3;
        // Box (0-2, 0-2) used = {3, 5, 9}.
        expect(candidatesFor(board, 0, 0).sort()).toEqual([1, 2, 4, 6, 7, 8]);
    });

    it('returns an empty array when the cell has no legal digits', () => {
        // Set up (0, 8) so its row, column, and box each already contain
        // every digit 1-9 in other cells. That leaves zero candidates.
        //
        // Row 0: 1, 2, 3, 4, 5, 6, 7, 8, 0   → row used: {1-8}
        // Col 8: 0, 1, 2, 3, 4, 5, 6, 7, 8   → col used: {1-8} (no 9 in col)
        //   ↑ but we need 9 in col/box. Place 9 at (1, 8).
        // Col 8: 0, 9, 1, 2, 3, 4, 5, 6, 7   → col used: {1-7, 9}
        //   ↑ but we need 8 too. Place 8 at (2, 8).
        // Col 8: 0, 9, 8, ?, ?, ?, ?, ?, ?   → col used: {8, 9}
        //   Hmm this is getting complex. The simplest: place 1-8 in row 0
        //   and 1-8 in col 8, and 1-7 in box. Then (0, 8) has 0 candidates
        //   only if 9 is in the box or col already.
        // Easier: place 1-8 in row 0 (cols 0-7), and 1-7 in col 8 (rows 0-7
        //   except 8, place 8 at row 1, col 8, and 9 at row 2, col 8).
        //   Col 8 used: {1-9} (wait, that's all 9).
        //
        // Let me try a cleaner approach. Set up so:
        //   Row 0: 1 2 3 4 5 6 7 8 _   → row used: {1-8}
        //   Col 8: _ 1 2 3 4 5 6 7 8   → col used: {1-8}
        //   Box (0-2, 6-8): _, _, _, 7, 8, _, 1, 2, 8 (row 0 col 6=7, col 7=8;
        //                            row 1 col 6=1, col 7=2, col 8=8;
        //                            row 2 col 6=?, col 7=?, col 8=?)
        //     Box has 7, 8, 1, 2 — need 3-9 missing
        //   For (0, 8) to have 0 candidates, box needs to have 9.
        //   Place 9 at (2, 8). Now box has {1, 2, 7, 8, 9}. Still missing 3-6.
        //   But col 8 now has {1, 2, 3, 4, 5, 6, 7, 8, 9} if we add 3-6.
        //   Hmm.
        //
        // The point is: this test case is intricate to construct. Use a
        // simpler-but-equivalent test: a cell with 0 candidates is rare in
        // well-formed puzzles. Construct one by filling row 0 with 1-8 and
        // col 8 with 1-8, then placing 9 anywhere in the box (0-2, 6-8).
        const board = Array.from({ length: 9 }, () => Array(9).fill(0));
        // Row 0: 1, 2, 3, 4, 5, 6, 7, 8, 0
        for (let c = 0; c < 8; c++) board[0][c] = c + 1;
        // Col 8: 0, 1, 2, 3, 4, 5, 6, 7, 8 (skip row 0)
        for (let r = 1; r < 9; r++) board[r][8] = r;
        // Box (0-2, 6-8) currently has: (0,6)=7, (0,7)=8, (1,8)=1, (2,8)=2.
        // Still need 3-9 in the box. But col 8 already has 1-8 (no 9).
        // Add 9 at (1, 7). Then box has {1, 2, 7, 8, 9}.
        // Now (0, 8) row used={1-8}, col used={1-9}, box used={1,2,7,8,9}.
        // All together: {1, 2, 3, 4, 5, 6, 7, 8, 9}. Zero candidates.
        board[1][7] = 9;
        // Wait — that puts 9 in col 7 of row 1. Now col 7 has: (0,7)=8, (1,7)=9.
        // Box (0-2, 6-8) now has (0,6)=7, (0,7)=8, (1,7)=9, (1,8)=1, (2,8)=2. Missing 3,4,5,6.
        // So box used = {1, 2, 7, 8, 9}, not all 1-9. We need more.
        // Fill (1, 6) = 3, (2, 6) = 4, (2, 7) = 5, (3, 8) = ... wait, (3, 8) is not in box 0-2.
        // Box (0-2, 6-8) has cells: (0,6), (0,7), (0,8), (1,6), (1,7), (1,8), (2,6), (2,7), (2,8).
        // Already filled: (0,6)=7, (0,7)=8, (1,6)=?, (1,7)=9, (1,8)=1, (2,6)=?, (2,7)=?, (2,8)=2.
        // Need 3, 4, 5, 6 in the remaining 3 cells: (1, 6), (2, 6), (2, 7).
        // (1, 6) is in row 1: row 1 has (1, 7)=9, (1, 8)=1. So 1, 9 used. (1, 6) can be any of 2-8.
        // (2, 6) is in row 2: row 2 has (2, 8)=2. So 2 used. (2, 6) can be any of 1, 3-9.
        // (2, 7) is in row 2: same as above, 2 used. (2, 7) can be any of 1, 3-9.
        // (2, 7) is in col 7: col 7 has (0, 7)=8, (1, 7)=9. So 8, 9 used. (2, 7) can be any of 1-7.
        // So (2, 7) options: {1, 3, 4, 5, 6, 7}.
        // Let's just fill (1, 6) = 3, (2, 6) = 4, (2, 7) = 5.
        board[1][6] = 3;
        board[2][6] = 4;
        board[2][7] = 5;
        // Now (0, 8):
        //   row 0 used: {1, 2, 3, 4, 5, 6, 7, 8} → available {9}
        //   col 8 used: {1, 2, 3, 4, 5, 6, 7, 8} → available {9}
        //   box (0-2, 6-8) used: 7, 8, 3, 9, 1, 4, 5, 2 = {1-9} → available {}
        // Zero candidates.
        expect(candidatesFor(board, 0, 8)).toEqual([]);
    });
});

describe('sodoku/hints — hint', () => {
    it('returns null when the board is already solved', () => {
        const board = [
            row(5, 3, 4, 6, 7, 8, 9, 1, 2),
            row(6, 7, 2, 1, 9, 5, 3, 4, 8),
            row(1, 9, 8, 3, 4, 2, 5, 6, 7),
            row(8, 5, 9, 7, 6, 1, 4, 2, 3),
            row(4, 2, 6, 8, 5, 3, 7, 9, 1),
            row(7, 1, 3, 9, 2, 4, 8, 5, 6),
            row(9, 6, 1, 5, 3, 7, 2, 8, 4),
            row(2, 8, 7, 4, 1, 9, 6, 3, 5),
            row(3, 4, 5, 2, 8, 6, 1, 7, 9),
        ];
        const solution = board.map((r) => r.slice());
        expect(hint(board, solution)).toBeNull();
    });

    it('returns the most-constrained empty cell (naked single) with its value', () => {
        // A simple puzzle where (0, 0) is the only empty cell with 1
        // candidate. (Hint: "naked single" — the cell has only one option.)
        const board = [
            row(5, 0, 0, 0, 0, 0, 0, 0, 0),
            row(0, 3, 0, 0, 0, 0, 0, 0, 0),
            row(0, 0, 9, 0, 0, 0, 0, 0, 0),
            row(0, 0, 0, 0, 0, 0, 0, 0, 0),
            row(0, 0, 0, 0, 0, 0, 0, 0, 0),
            row(0, 0, 0, 0, 0, 0, 0, 0, 0),
            row(0, 0, 0, 0, 0, 0, 0, 0, 0),
            row(0, 0, 0, 0, 0, 0, 0, 0, 0),
            row(0, 0, 0, 0, 0, 0, 0, 0, 0),
        ];
        const solution = solveBoard(board)!;
        // (0, 0) is a naked single (it's the only 5 in row 0, col 0, and box 0).
        // But there may be other naked singles — the function should return
        // *some* valid hint. The most-constrained heuristic picks the cell
        // with the fewest candidates.
        const result = hint(board, solution);
        expect(result).not.toBeNull();
        expect(result!.row).toBeGreaterThanOrEqual(0);
        expect(result!.row).toBeLessThan(9);
        expect(result!.col).toBeGreaterThanOrEqual(0);
        expect(result!.col).toBeLessThan(9);
        expect(result!.value).toBe(solution[result!.row][result!.col]);
        // The cell must actually be empty.
        expect(board[result!.row][result!.col]).toBe(0);
    });

    it('finds a cell with only 1 candidate and labels it "Naked Single"', () => {
        // (0, 1) is the only place 4 can go in row 0 (because (0, 0)=1
        // means row 0 has 1, and (0, 1) is otherwise free). But that's a
        // hidden single, not a naked one. Set up a true naked single:
        // a cell with exactly 1 candidate.
        const board = [
            row(1, 2, 3, 4, 5, 6, 7, 8, 0), // (0, 8) is the only 9 in row 0
            row(0, 0, 0, 0, 0, 0, 0, 0, 0),
            row(0, 0, 0, 0, 0, 0, 0, 0, 0),
            row(0, 0, 0, 0, 0, 0, 0, 0, 0),
            row(0, 0, 0, 0, 0, 0, 0, 0, 0),
            row(0, 0, 0, 0, 0, 0, 0, 0, 0),
            row(0, 0, 0, 0, 0, 0, 0, 0, 0),
            row(0, 0, 0, 0, 0, 0, 0, 0, 0),
            row(0, 0, 0, 0, 0, 0, 0, 0, 0),
        ];
        const solution = solveBoard(board)!;
        const result = hint(board, solution);
        expect(result).not.toBeNull();
        // (0, 8) is a naked single (row 0 has 1-8, so col 8 must be 9).
        // Other cells have 8-9 candidates, so (0, 8) is the most constrained.
        expect(result!.row).toBe(0);
        expect(result!.col).toBe(8);
        expect(result!.value).toBe(9);
        expect(result!.technique).toBe('Naked Single');
    });
});
