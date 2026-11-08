/**
 * Mistake accounting for the Sodoku "Check" action.
 *
 * A mistake is one *wrong entry*: a non-given cell holding a digit that
 * differs from the solution. Wrong entries are cumulative — three separate
 * mistakes must reach the 3-mistake loss limit — but a given wrong entry is
 * charged only once, so pressing Check repeatedly on an unchanged board is
 * free. Clearing a cell's charges when its value changes makes a re-entered
 * wrong digit a new mistake.
 */

export interface MistakeCounter {
    mistakes: number;
    chargedWrongKeys: string[];
}

/**
 * Stable keys for the currently wrong entries, e.g. "0,3:7" for row 0 column
 * 3 holding a 7. The digit is part of the key so changing a wrong value to a
 * different wrong value counts as a new mistake.
 */
export function wrongCellKeys(
    board: number[][],
    given: boolean[][],
    solution: number[][],
): string[] {
    const keys: string[] = [];
    for (let r = 0; r < board.length; r++) {
        for (let c = 0; c < board[r].length; c++) {
            const value = board[r][c];
            if (value !== 0 && !given[r][c] && value !== solution[r][c]) {
                keys.push(`${r},${c}:${value}`);
            }
        }
    }
    return keys;
}

/** Forget charges for a cell whose value just changed (set, erased, undone). */
export function clearCellCharges(counter: MistakeCounter, r: number, c: number): void {
    const prefix = `${r},${c}:`;
    counter.chargedWrongKeys = counter.chargedWrongKeys.filter((key) => !key.startsWith(prefix));
}

/**
 * Charge one mistake per wrong entry that has not been charged yet.
 * Returns the number of newly charged mistakes.
 */
export function chargeMistakes(
    counter: MistakeCounter,
    board: number[][],
    given: boolean[][],
    solution: number[][],
): number {
    const fresh = wrongCellKeys(board, given, solution)
        .filter((key) => !counter.chargedWrongKeys.includes(key));

    counter.mistakes += fresh.length;
    counter.chargedWrongKeys.push(...fresh);

    return fresh.length;
}
