import { describe, it, expect } from 'vitest';
import {
    createBoard,
    cloneBoard,
    getMatches,
    swapCells,
    clearMatches,
    applyGravity,
    hasPossibleMoves,
    resolveCascades,
    areAdjacent,
    createCell,
} from '@/composables/useIconCrush';

const ICONS = ['a', 'b', 'c', 'd', 'e'];

function boardFrom(strings: string[]): ReturnType<typeof createBoard> {
    return strings.map((row) => row.split('').map((ch) => (ch === ' ' ? null : createCell(ch))));
}

function icons(board: ReturnType<typeof createBoard>): string[][] {
    return board.map((row) => row.map((cell) => (cell ? cell.icon : ' ')));
}

describe('useIconCrush logic', () => {
    it('creates a board without initial matches', () => {
        const board = createBoard(8, 8, ICONS);
        expect(board.length).toBe(8);
        expect(board[0].length).toBe(8);
        expect(getMatches(board).size).toBe(0);
    });

    it('detects horizontal and vertical matches', () => {
        const board = boardFrom([
            'aaabc',
            'bccde',
            'bccde',
            'bddef',
            'eeeff',
        ]);
        const matches = getMatches(board);
        expect(matches.has('0,0')).toBe(true);
        expect(matches.has('0,1')).toBe(true);
        expect(matches.has('0,2')).toBe(true);
        expect(matches.has('1,0')).toBe(true);
        expect(matches.has('2,0')).toBe(true);
        expect(matches.has('3,0')).toBe(true);
        expect(matches.has('4,0')).toBe(true);
        expect(matches.has('4,1')).toBe(true);
        expect(matches.has('4,2')).toBe(true);
    });

    it('swaps two adjacent cells', () => {
        const board = boardFrom([
            'ab',
            'cd',
        ]);
        swapCells(board, { r: 0, c: 0 }, { r: 0, c: 1 });
        expect(icons(board)).toEqual([
            ['b', 'a'],
            ['c', 'd'],
        ]);
    });

    it('clears matched cells and applies gravity', () => {
        const board = boardFrom([
            'aaa',
            'bcd',
            'efg',
        ]);
        const matches = getMatches(board);
        clearMatches(board, matches);
        applyGravity(board, ICONS);
        expect(board[2][0]).not.toBeNull();
        expect(board[1][0]).not.toBeNull();
        expect(board[0][0]).not.toBeNull();
    });

    it('resolves cascades and scores combos', () => {
        const board = boardFrom([
            'aaab',
            'cdeb',
            'fgeb',
            'hijk',
        ]);
        const { score, maxCombo } = resolveCascades(board, ICONS);
        expect(score).toBeGreaterThan(0);
        expect(maxCombo).toBeGreaterThanOrEqual(1);
    });

    it('knows when no moves are possible', () => {
        const board = boardFrom([
            'abc',
            'bca',
            'cab',
        ]);
        expect(hasPossibleMoves(board)).toBe(false);
    });

    it('knows when a move is possible', () => {
        const board = boardFrom([
            'aab',
            'bbc',
            'cca',
        ]);
        expect(hasPossibleMoves(board)).toBe(true);
    });

    it('reports adjacency correctly', () => {
        expect(areAdjacent({ r: 0, c: 0 }, { r: 0, c: 1 })).toBe(true);
        expect(areAdjacent({ r: 0, c: 0 }, { r: 1, c: 0 })).toBe(true);
        expect(areAdjacent({ r: 0, c: 0 }, { r: 0, c: 0 })).toBe(false);
        expect(areAdjacent({ r: 0, c: 0 }, { r: 1, c: 1 })).toBe(false);
    });

    it('clones a board deeply enough for simulations', () => {
        const board = boardFrom([
            'abcd',
            'bcde',
            'cdea',
            'deab',
        ]);
        const copy = cloneBoard(board);
        swapCells(copy, { r: 0, c: 0 }, { r: 0, c: 1 });
        expect(board[0][0]!.icon).toBe('a');
        expect(copy[0][0]!.icon).toBe('b');
    });
});
