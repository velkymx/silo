/**
 * Solve a 9×9 Sodoku board via backtracking with constraint propagation.
 *
 * Input: 9×9 number[][] where 0 = empty.
 * Output: 9×9 number[][] of the unique solution, or null if unsolvable.
 *
 * Performance target: < 100 ms for beginner (40+ givens), < 500 ms for
 * advanced (17-22 givens) on a modern V8. Verified by `solver.spec.ts`.
 *
 * Strategy:
 *   1. Build used-set tables (row/col/box) once from the given board.
 *   2. At each recursion, pick the empty cell with the fewest candidates
 *      (MRV heuristic) — this shrinks the search tree by an order of magnitude
 *      on hard boards.
 *   3. Try each candidate, recurse, backtrack on failure.
 *
 * The solver ONLY ever mutates a single cell at a time, so backtrack is
 * a constant-cost undo: clear the cell and remove the digit from the three
 * used sets. Peer cells are never touched.
 */

const BOX = 3;
const SIZE = 9;
const TOTAL = SIZE * SIZE;

function boxIndex(r: number, c: number): number {
    return Math.floor(r / BOX) * BOX + Math.floor(c / BOX);
}

interface SolverState {
    board: number[][];
    rowUsed: Uint8Array[]; // 1 if digit (1-9) is used in row r
    colUsed: Uint8Array[];
    boxUsed: Uint8Array[];
    empties: Uint8Array; // 1D-flattened index of each empty cell
}

function buildState(board: number[][]): SolverState | null {
    const rowUsed = Array.from({ length: SIZE }, () => new Uint8Array(SIZE + 1));
    const colUsed = Array.from({ length: SIZE }, () => new Uint8Array(SIZE + 1));
    const boxUsed = Array.from({ length: SIZE }, () => new Uint8Array(SIZE + 1));
    const empties: number[] = [];

    for (let r = 0; r < SIZE; r++) {
        for (let c = 0; c < SIZE; c++) {
            const v = board[r][c];
            if (v === 0) {
                empties.push(r * SIZE + c);
            } else {
                // Pre-validate: a given must not duplicate another given in
                // the same row, column, or 3×3 box. This is the difference
                // between an "unsolvable puzzle" and a "puzzle that has
                // been given contradictory givens" — we want both to
                // surface as `null` from the solver's perspective.
                if (rowUsed[r][v] | colUsed[c][v] | boxUsed[boxIndex(r, c)][v]) {
                    return null;
                }
                rowUsed[r][v] = 1;
                colUsed[c][v] = 1;
                boxUsed[boxIndex(r, c)][v] = 1;
            }
        }
    }

    return {
        board,
        rowUsed,
        colUsed,
        boxUsed,
        empties: new Uint8Array(empties),
    };
}

/** Build the 81-bit candidates mask for cell (r, c) given the used sets. */
function candidatesMask(state: SolverState, r: number, c: number): number {
    const b = boxIndex(r, c);
    let used = 0;
    for (let v = 1; v <= SIZE; v++) {
        if (state.rowUsed[r][v] | state.colUsed[c][v] | state.boxUsed[b][v]) {
            used |= 1 << v;
        }
    }
    return (~used) & 0x3FE; // 0b1111111110 — bits 1..9 set
}

/** Find the empty cell with the fewest candidates (popcount of mask). */
function nextCell(state: SolverState): { idx: number; mask: number } | null {
    let bestIdx = -1;
    let bestMask = 0;
    let bestCount = SIZE + 1;
    for (const idx of state.empties) {
        if (state.board[Math.floor(idx / SIZE)][idx % SIZE] !== 0) continue;
        const mask = candidatesMask(state, Math.floor(idx / SIZE), idx % SIZE);
        const count = popcount(mask);
        if (count === 0) return { idx, mask: 0 };
        if (count < bestCount) {
            bestIdx = idx;
            bestMask = mask;
            bestCount = count;
            if (count === 1) break;
        }
    }
    return bestIdx === -1 ? null : { idx: bestIdx, mask: bestMask };
}

function popcount(m: number): number {
    let c = 0;
    while (m) {
        m &= m - 1;
        c++;
    }
    return c;
}

function solve(state: SolverState): boolean {
    const cell = nextCell(state);
    if (!cell) return true; // no empty cells left → solved
    const { idx, mask } = cell;
    const r = Math.floor(idx / SIZE);
    const c = idx % SIZE;
    const b = boxIndex(r, c);

    // Try each candidate bit in `mask` (in numeric order, which matches
    // the natural puzzle-solving heuristic: try small digits first).
    for (let v = 1; v <= SIZE; v++) {
        if (!(mask & (1 << v))) continue;
        state.board[r][c] = v;
        state.rowUsed[r][v] = 1;
        state.colUsed[c][v] = 1;
        state.boxUsed[b][v] = 1;

        if (solve(state)) return true;

        // Backtrack: undo the single cell we just filled.
        state.board[r][c] = 0;
        state.rowUsed[r][v] = 0;
        state.colUsed[c][v] = 0;
        state.boxUsed[b][v] = 0;
    }

    return false;
}

export function solveBoard(board: number[][]): number[][] | null {
    if (board.length !== SIZE) return null;
    for (const r of board) if (r.length !== SIZE) return null;

    // Defensive deep copy — never mutate the caller's board.
    const copy = board.map((r) => r.slice());
    const state = buildState(copy);
    if (!state) return null; // conflicting givens
    return solve(state) ? copy : null;
}

/**
 * Check whether placing `value` in cell (r, c) would violate any
 * row / column / 3×3-box constraint. Used by the frontend for:
 *  - real-time error highlighting as the user types
 *  - the "check" button that auto-marks incorrect placements in red
 *
 * The value at (r, c) itself is ignored — the question is "would this
 * conflict with anything else on the board?"
 */
export function isValidPlacement(
    board: number[][],
    r: number,
    c: number,
    value: number
): boolean {
    if (value < 1 || value > 9) return false;
    if (board[r][c] === value) return true; // no-op: same value
    const b = boxIndex(r, c);
    for (let k = 0; k < 9; k++) {
        if (k !== c && board[r][k] === value) return false; // same row
        if (k !== r && board[k][c] === value) return false; // same column
    }
    const br = Math.floor(r / BOX) * BOX;
    const bc = Math.floor(c / BOX) * BOX;
    for (let dr = 0; dr < BOX; dr++) {
        for (let dc = 0; dc < BOX; dc++) {
            const rr = br + dr;
            const cc = bc + dc;
            if ((rr !== r || cc !== c) && board[rr][cc] === value) return false; // same box
        }
    }
    return true;
}

/**
 * Like `isValidPlacement`, but ignores whatever is currently in cell (r, c).
 * Used by `candidatesFor` to ask "what other values could I place here?"
 * regardless of the current value of the cell.
 */
function isLegalIgnoringSelf(
    board: number[][],
    r: number,
    c: number,
    value: number
): boolean {
    if (value < 1 || value > 9) return false;
    const b = boxIndex(r, c);
    for (let k = 0; k < 9; k++) {
        if (board[r][k] === value) return false; // same row
        if (board[k][c] === value) return false; // same column
    }
    const br = Math.floor(r / BOX) * BOX;
    const bc = Math.floor(c / BOX) * BOX;
    for (let dr = 0; dr < BOX; dr++) {
        for (let dc = 0; dc < BOX; dc++) {
            const rr = br + dr;
            const cc = bc + dc;
            if (board[rr][cc] === value) return false; // same box
        }
    }
    return true;
}

/**
 * Compare the player's filled cells against the puzzle's true solution.
 * Returns the list of (r, c) cells where the player is wrong. Empty cells
 * (zeros) are skipped — the caller should ensure the board is fully
 * filled before checking.
 *
 * Used by the win flow: when the user has filled every cell, compare
 * against the server's known solution. If the list is empty, they win.
 */
export function validateSolution(
    puzzle: number[][],
    player: number[][]
): Array<[number, number]> {
    const errors: Array<[number, number]> = [];
    for (let r = 0; r < 9; r++) {
        for (let c = 0; c < 9; c++) {
            if (player[r][c] !== 0 && player[r][c] !== puzzle[r][c]) {
                errors.push([r, c]);
            }
        }
    }
    return errors;
}

/**
 * Return the set of digits that could legally be placed in cell (r, c)
 * given the current board state. Used to render the pencil-mark
 * overlay in the UI.
 */
export function candidatesFor(
    board: number[][],
    r: number,
    c: number
): number[] {
    const candidates: number[] = [];
    for (let v = 1; v <= 9; v++) {
        if (isLegalIgnoringSelf(board, r, c, v)) candidates.push(v);
    }
    return candidates;
}

export interface Hint {
    row: number;
    col: number;
    value: number;
    technique: 'Naked Single' | 'Hidden Single' | 'Best Guess';
}

/**
 * Find the most "stuck" empty cell and reveal the correct digit.
 * Returns null if the board is already solved.
 *
 * The strategy is: find the empty cell with the fewest candidates, fill
 * it from the known solution, and label the technique. The label is
 * `Naked Single` when the cell has only 1 candidate (i.e. the user
 * should have found this themselves); otherwise `Best Guess` is honest
 * about the fact that this requires deeper analysis.
 *
 * `solution` is the known-good 9×9 solution. The hint does NOT solve
 * the board (that's a separate operation); it relies on the caller
 * having the solution in hand.
 */
export function hint(board: number[][], solution: number[][]): Hint | null {
    // Find the most-constrained empty cell.
    let bestR = -1;
    let bestC = -1;
    let bestCount = 10; // > 9 so any real cell is better
    for (let r = 0; r < 9; r++) {
        for (let c = 0; c < 9; c++) {
            if (board[r][c] !== 0) continue;
            const n = candidatesFor(board, r, c).length;
            if (n === 0) continue; // dead cell — skip
            if (n < bestCount) {
                bestR = r;
                bestC = c;
                bestCount = n;
                if (n === 1) break;
            }
        }
        if (bestCount === 1) break;
    }

    if (bestR === -1) return null; // board is fully filled

    const technique: Hint['technique'] = bestCount === 1 ? 'Naked Single' : 'Best Guess';
    return {
        row: bestR,
        col: bestC,
        value: solution[bestR][bestC],
        technique,
    };
}
