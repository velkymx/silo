import { describe, it, expect } from 'vitest';
import { solveBoard } from '@/lib/sodoku/solver';
import { getSudoku } from 'sudoku-gen';

const row = (...vals: number[]) => vals;

describe('sodoku/solver', () => {
    it('returns null for a board with a row violation', () => {
        const bad = Array.from({ length: 9 }, () => Array(9).fill(0));
        bad[0][0] = 1;
        bad[0][3] = 1;
        expect(solveBoard(bad)).toBeNull();
    });

    it('returns null for a board with a column violation', () => {
        const bad = Array.from({ length: 9 }, () => Array(9).fill(0));
        bad[0][0] = 1;
        bad[3][0] = 1;
        expect(solveBoard(bad)).toBeNull();
    });

    it('returns null for a board with a box violation', () => {
        const bad = Array.from({ length: 9 }, () => Array(9).fill(0));
        bad[0][0] = 1;
        bad[1][1] = 1;
        expect(solveBoard(bad)).toBeNull();
    });

    it('solves a known easy board from Wikipedia', () => {
        // https://en.wikipedia.org/wiki/Sudoku#Sample_puzzle (30 givens)
        const puzzle = [
            row(5, 3, 0, 0, 7, 0, 0, 0, 0),
            row(6, 0, 0, 1, 9, 5, 0, 0, 0),
            row(0, 9, 8, 0, 0, 0, 0, 6, 0),
            row(8, 0, 0, 0, 6, 0, 0, 0, 3),
            row(4, 0, 0, 8, 0, 3, 0, 0, 1),
            row(7, 0, 0, 0, 2, 0, 0, 0, 6),
            row(0, 6, 0, 0, 0, 0, 2, 8, 0),
            row(0, 0, 0, 4, 1, 9, 0, 0, 5),
            row(0, 0, 0, 0, 8, 0, 0, 7, 9),
        ];
        const expected = [
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
        const t = Date.now();
        const got = solveBoard(puzzle);
        const ms = Date.now() - t;
        expect(got).toEqual(expected);
        expect(ms).toBeLessThan(100);
    });

    it('solves a nearly-complete board in < 50 ms', () => {
        // 80 givens (one missing cell)
        const puzzle: number[][] = [
            row(5, 3, 4, 6, 7, 8, 9, 1, 2),
            row(6, 7, 2, 1, 9, 5, 3, 4, 8),
            row(1, 9, 8, 3, 4, 2, 5, 6, 7),
            row(8, 5, 9, 7, 6, 1, 4, 2, 3),
            row(4, 2, 6, 8, 5, 3, 7, 9, 1),
            row(7, 1, 3, 9, 2, 4, 8, 5, 6),
            row(9, 6, 1, 5, 3, 7, 2, 8, 4),
            row(2, 8, 7, 4, 1, 9, 6, 3, 5),
            row(3, 4, 5, 2, 8, 6, 1, 7, 0), // one missing
        ];
        const t = Date.now();
        const solution = solveBoard(puzzle);
        const ms = Date.now() - t;
        expect(solution).not.toBeNull();
        expect(solution![8][8]).toBe(9);
        expect(ms).toBeLessThan(50);
    });

    it('does not mutate the input board', () => {
        const puzzle = [
            row(5, 3, 0, 0, 7, 0, 0, 0, 0),
            row(6, 0, 0, 1, 9, 5, 0, 0, 0),
            row(0, 9, 8, 0, 0, 0, 0, 6, 0),
            row(8, 0, 0, 0, 6, 0, 0, 0, 3),
            row(4, 0, 0, 8, 0, 3, 0, 0, 1),
            row(7, 0, 0, 0, 2, 0, 0, 0, 6),
            row(0, 6, 0, 0, 0, 0, 2, 8, 0),
            row(0, 0, 0, 4, 1, 9, 0, 0, 5),
            row(0, 0, 0, 0, 8, 0, 0, 7, 9),
        ];
        const snapshot = puzzle.map((r) => r.slice());
        solveBoard(puzzle);
        expect(puzzle).toEqual(snapshot);
    });

    it('solves sudoku-gen "hard" puzzles in < 50 ms', () => {
        // 28 givens — the upper end of what the game ships.
        for (let i = 0; i < 5; i++) {
            const s = getSudoku('hard');
            const board = parsePuzzleString(s.puzzle);
            const t = Date.now();
            const sol = solveBoard(board);
            const ms = Date.now() - t;
            expect(sol).not.toBeNull();
            expect(ms).toBeLessThan(50);
        }
    });

    it('solves sudoku-gen "medium" puzzles quickly (< 50 ms)', () => {
        for (let i = 0; i < 5; i++) {
            const s = getSudoku('medium');
            const board = parsePuzzleString(s.puzzle);
            const t = Date.now();
            const sol = solveBoard(board);
            const ms = Date.now() - t;
            expect(sol).not.toBeNull();
            // Wall-clock bound loose enough for slower CI runners — a real
            // regression would be orders of magnitude slower, not 10-15ms.
            expect(ms).toBeLessThan(50);
        }
    });

    it('solves sudoku-gen "easy" puzzles in < 5 ms', () => {
        for (let i = 0; i < 5; i++) {
            const s = getSudoku('easy');
            const board = parsePuzzleString(s.puzzle);
            const t = Date.now();
            const sol = solveBoard(board);
            const ms = Date.now() - t;
            expect(sol).not.toBeNull();
            expect(ms).toBeLessThan(5);
        }
    });
});

/** Parse an 81-char puzzle string (digits and '-') into a 9×9 array. */
function parsePuzzleString(puzzle: string): number[][] {
    const board = Array.from({ length: 9 }, () => Array(9).fill(0));
    for (let i = 0; i < 81; i++) {
        const ch = puzzle[i];
        if (ch !== '-' && ch !== ' ' && ch !== '0') {
            board[Math.floor(i / 9)][i % 9] = parseInt(ch, 10);
        }
    }
    return board;
}
