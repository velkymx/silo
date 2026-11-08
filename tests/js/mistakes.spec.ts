import { describe, it, expect } from 'vitest';
import { chargeMistakes, clearCellCharges, wrongCellKeys } from '@/lib/sodoku/mistakes';

const solution = Array.from({ length: 9 }, () => Array(9).fill(1));
const given = Array.from({ length: 9 }, () => Array(9).fill(false));
const empty = () => Array.from({ length: 9 }, () => Array(9).fill(0));

function boardWith(...entries: Array<[number, number, number]>): number[][] {
    const board = empty();
    for (const [r, c, v] of entries) board[r][c] = v;
    return board;
}

describe('sodoku mistake accounting', () => {
    it('lists only non-given cells whose value differs from the solution', () => {
        const board = boardWith([0, 0, 2], [1, 1, 1]);
        const givenWithPreset = given.map((row) => [...row]);
        givenWithPreset[1][1] = true;

        expect(wrongCellKeys(board, givenWithPreset, solution)).toEqual(['0,0:2']);
    });

    it('accumulates separate mistakes across checks instead of taking a high-water mark', () => {
        const counter = { mistakes: 0, chargedWrongKeys: [] as string[] };

        // First wrong entry, then Check.
        chargeMistakes(counter, boardWith([0, 0, 2]), given, solution);
        expect(counter.mistakes).toBe(1);

        // Fix it, make a different wrong entry, Check again -> second mistake.
        clearCellCharges(counter, 0, 0);
        chargeMistakes(counter, boardWith([0, 1, 3]), given, solution);
        expect(counter.mistakes).toBe(2);

        // A third separate mistake must reach MAX_MISTAKES (3).
        clearCellCharges(counter, 0, 1);
        chargeMistakes(counter, boardWith([0, 2, 4]), given, solution);
        expect(counter.mistakes).toBe(3);
    });

    it('does not double-charge the same wrong entry on repeated checks', () => {
        const counter = { mistakes: 0, chargedWrongKeys: [] as string[] };
        const board = boardWith([0, 0, 2], [0, 1, 3]);

        chargeMistakes(counter, board, given, solution);
        chargeMistakes(counter, board, given, solution);
        chargeMistakes(counter, board, given, solution);

        expect(counter.mistakes).toBe(2);
    });

    it('charges again when the same wrong digit is re-entered after a fix', () => {
        const counter = { mistakes: 0, chargedWrongKeys: [] as string[] };

        chargeMistakes(counter, boardWith([0, 0, 2]), given, solution);
        clearCellCharges(counter, 0, 0); // player fixed the cell
        chargeMistakes(counter, boardWith([0, 0, 2]), given, solution);

        expect(counter.mistakes).toBe(2);
    });

    it('counts multiple wrong cells revealed by one check', () => {
        const counter = { mistakes: 0, chargedWrongKeys: [] as string[] };

        chargeMistakes(counter, boardWith([0, 0, 2], [0, 1, 3], [0, 2, 4]), given, solution);

        expect(counter.mistakes).toBe(3);
    });
});
