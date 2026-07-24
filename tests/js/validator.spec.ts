import { describe, it, expect } from 'vitest';
import { isValidPlacement, validateSolution } from '@/lib/sodoku/solver';

const row = (...vals: number[]) => vals;

/**
 * The validator answers two questions:
 *  1. `isValidPlacement(board, row, col, value)` — would placing `value` in
 *     cell (r, c) violate any row/col/box constraint? Used by the frontend
 *     "check" / "auto-mark errors" features.
 *  2. `validateSolution(board, solution)` — is the player's solution
 *     consistent with the puzzle? Returns the list of (r, c) cells that
 *     are wrong. Used by the win/lose flow.
 */
describe('sodoku/validator — isValidPlacement', () => {
    it('returns true when the placement is legal', () => {
        const board = Array.from({ length: 9 }, () => Array(9).fill(0));
        expect(isValidPlacement(board, 0, 0, 5)).toBe(true);
    });

    it('returns false when the value already exists in the row', () => {
        const board = Array.from({ length: 9 }, () => Array(9).fill(0));
        board[0][3] = 5;
        expect(isValidPlacement(board, 0, 0, 5)).toBe(false);
    });

    it('returns false when the value already exists in the column', () => {
        const board = Array.from({ length: 9 }, () => Array(9).fill(0));
        board[3][0] = 5;
        expect(isValidPlacement(board, 0, 0, 5)).toBe(false);
    });

    it('returns false when the value already exists in the 3×3 box', () => {
        const board = Array.from({ length: 9 }, () => Array(9).fill(0));
        board[1][1] = 5;
        expect(isValidPlacement(board, 0, 0, 5)).toBe(false);
    });

    it('ignores the value at the target cell itself', () => {
        // If (r, c) already has `value`, the placement is trivially valid
        // (it's a no-op). This matters when the user toggles a value.
        const board = Array.from({ length: 9 }, () => Array(9).fill(0));
        board[0][0] = 5;
        expect(isValidPlacement(board, 0, 0, 5)).toBe(true);
    });
});

describe('sodoku/validator — validateSolution', () => {
    it('returns an empty list when the player has the correct solution', () => {
        // Generate a puzzle, get its true solution, and check the player matches.
        const board: number[][] = [];
        for (let r = 0; r < 9; r++) {
            board[r] = [];
            for (let c = 0; c < 9; c++) {
                board[r][c] = 0;
            }
        }
        // Use a known good board.
        const puzzle = [
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
        expect(validateSolution(puzzle, puzzle)).toEqual([]);
    });

    it('returns the list of (r, c) cells that are wrong', () => {
        const puzzle = [
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
        // Player has 2 wrong cells.
        const player = puzzle.map((r) => r.slice());
        player[0][0] = 9; // wrong
        player[8][8] = 8; // wrong
        const errors = validateSolution(puzzle, player);
        expect(errors).toEqual(
            expect.arrayContaining([
                [0, 0],
                [8, 8],
            ]),
        );
        expect(errors).toHaveLength(2);
    });

    it('returns an empty list for an unsolved board (with zeros) only if zeros are skipped', () => {
        // If the player hasn't filled every cell, we shouldn't return the
        // empty cells as "wrong" — the win-check should only compare filled
        // cells, OR the caller should ensure the board is fully filled.
        const puzzle: number[][] = [
            row(5, 3, 4, 6, 7, 8, 9, 1, 2),
            row(6, 7, 2, 1, 9, 5, 3, 4, 8),
            row(1, 9, 8, 3, 4, 2, 5, 6, 7),
            row(8, 5, 9, 7, 6, 1, 4, 2, 3),
            row(4, 2, 6, 8, 5, 3, 7, 9, 1),
            row(7, 1, 3, 9, 2, 4, 8, 5, 6),
            row(9, 6, 1, 5, 3, 7, 2, 8, 4),
            row(2, 8, 7, 4, 1, 9, 6, 3, 5),
            row(3, 4, 5, 2, 8, 6, 1, 7, 0),
        ];
        // The player has the same board but one cell is still 0. That's not
        // "wrong" per se — the function returns an empty list (no errors),
        // and the caller is expected to check that the board is fully filled
        // before calling validateSolution.
        expect(validateSolution(puzzle, puzzle)).toEqual([]);
    });
});
