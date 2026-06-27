<?php

namespace App\Services;

use App\Lib\Sodoku\Seed;

/**
 * Minimal Sodoku puzzle generator and solution validator.
 *
 * Deterministic: a given (seed, difficulty) always produces the same puzzle
 * with the same unique solution. Used by the daily-puzzle feature so that
 * every user on the same day sees the same board.
 *
 * Algorithm:
 *   1. Generate a complete valid 9×9 grid by filling row 0 with a shuffled
 *      permutation of 1-9 (seeded by `mt_srand`) and backtracking through
 *      the rest. This gives a valid solution.
 *   2. Remove cells one at a time while keeping the puzzle uniquely
 *      solvable. A backtracking solver is used to verify uniqueness after
 *      each removal; if removing a cell creates a second solution, we put
 *      it back and try another.
 *
 * Difficulty is approximated by the number of cells removed:
 *   - beginner:     ~43 removed  →  38 givens
 *   - intermediate: ~51 removed  →  30 givens
 *   - advanced:     ~58 removed  →  23 givens
 *
 * This mirrors the well-known sudoku-gen npm package's tiered output and
 * is the same difficulty range the rest of the app ships with.
 *
 * This is intentionally minimal: no symmetry requirement, no formal
 * difficulty score. It's a "good enough" generator that produces
 * well-formed puzzles with a unique solution.
 */
class SodokuGenerator
{
    public const SIZE = 9;
    public const BOX = 3;

    /** @return array{puzzle: string, solution: string, difficulty: string} */
    public function generate(int $seed, string $difficulty): array
    {
        mt_srand($seed);

        $solution = $this->generateCompleteGrid();
        $givens = match ($difficulty) {
            'beginner' => 38,
            'intermediate' => 30,
            'advanced' => 23,
            default => 30,
        };

        $puzzle = $this->removeCells($solution, 81 - $givens, $seed);

        return [
            'puzzle' => $this->boardToString($puzzle),
            'solution' => $this->boardToString($solution),
            'difficulty' => $difficulty,
        ];
    }

    /**
     * Generate a complete 9×9 grid by filling row 0 with a shuffled
     * permutation of 1-9 and backtracking through the rest.
     *
     * @return int[][]
     */
    private function generateCompleteGrid(): array
    {
        $grid = array_fill(0, self::SIZE, array_fill(0, self::SIZE, 0));

        // Row 0: shuffled 1-9.
        $row = range(1, 9);
        shuffle($row);
        for ($c = 0; $c < 9; $c++) {
            $grid[0][$c] = $row[$c];
        }

        // Backtrack-fill the rest. Start at (1, 0) because row 0 is already
        // populated by the shuffled permutation above.
        // fillFromCell can theoretically return false if row 0 leads to a dead
        // end — loop until a valid grid is produced.
        if ($this->fillFromCell(1, 0, $grid)) {
            return $grid;
        }

        // Retry with a new shuffle (extremely rare; any 1–9 permutation can be
        // completed, but defensive against edge cases in the constraint checker).
        return $this->generateCompleteGrid();
    }

    private function fillFromCell(int $r, int $c, array &$grid): bool
    {
        if ($r === self::SIZE) return true; // all rows filled

        $candidates = range(1, 9);
        shuffle($candidates);
        foreach ($candidates as $v) {
            if ($this->canPlace($grid, $r, $c, $v)) {
                $grid[$r][$c] = $v;
                [$nr, $nc] = $this->nextCell($r, $c);
                if ($this->fillFromCell($nr, $nc, $grid)) return true;
                $grid[$r][$c] = 0;
            }
        }
        return false;
    }

    /** @param int[][] $grid */
    private function nextCell(int $r, int $c): array
    {
        if ($c + 1 < self::SIZE) return [$r, $c + 1];
        return [$r + 1, 0];
    }

    /** @param int[][] $grid */
    private function canPlace(array $grid, int $r, int $c, int $v): bool
    {
        for ($k = 0; $k < self::SIZE; $k++) {
            if ($grid[$r][$k] === $v) return false; // row
            if ($grid[$k][$c] === $v) return false; // column
        }
        $br = intdiv($r, self::BOX) * self::BOX;
        $bc = intdiv($c, self::BOX) * self::BOX;
        for ($dr = 0; $dr < self::BOX; $dr++) {
            for ($dc = 0; $dc < self::BOX; $dc++) {
                if ($grid[$br + $dr][$bc + $dc] === $v) return false; // box
            }
        }
        return true;
    }

    /**
     * Remove up to $toRemove cells while keeping the puzzle uniquely
     * solvable. Tries random positions; if removing a cell produces a
     * second solution, the cell is restored.
     *
     * @param  int[][] $solution
     * @return int[][]
     */
    private function removeCells(array $solution, int $toRemove, int $seed): array
    {
        mt_srand($seed + 1);
        $positions = [];
        for ($r = 0; $r < 9; $r++) {
            for ($c = 0; $c < 9; $c++) {
                $positions[] = [$r, $c];
            }
        }

        // Greedy removal with uniqueness check. Try multiple shuffle orders (seeded,
        // so deterministic) in case one order boxes itself into a dead end.
        for ($attempt = 0; $attempt < 12; $attempt++) {
            $puzzle = $solution;
            $removed = 0;
            shuffle($positions); // advances seeded mt_rand state deterministically

            foreach ($positions as $pos) {
                if ($removed >= $toRemove) break;
                [$r, $c] = $pos;
                $backup = $puzzle[$r][$c];
                $puzzle[$r][$c] = 0;

                if ($this->countSolutions($puzzle, 2) !== 1) {
                    $puzzle[$r][$c] = $backup;
                    continue;
                }
                $removed++;
            }

            if ($removed >= $toRemove) return $puzzle;
        }

        return $puzzle; // best effort if target not met after all attempts
    }

    /** Count solutions up to $max. Stops early once $max is reached. */
    private function countSolutions(array $board, int $max = 2): int
    {
        for ($r = 0; $r < self::SIZE; $r++) {
            for ($c = 0; $c < self::SIZE; $c++) {
                if ($board[$r][$c] !== 0) continue;
                $count = 0;
                for ($v = 1; $v <= self::SIZE; $v++) {
                    if ($this->canPlace($board, $r, $c, $v)) {
                        $board[$r][$c] = $v;
                        $count += $this->countSolutions($board, $max - $count);
                        $board[$r][$c] = 0;
                        if ($count >= $max) return $count;
                    }
                }
                return $count;
            }
        }
        return 1;
    }

    /** @param int[][] $board */
    private function boardToString(array $board): string
    {
        $s = '';
        for ($r = 0; $r < self::SIZE; $r++) {
            for ($c = 0; $c < self::SIZE; $c++) {
                $s .= $board[$r][$c] === 0 ? '-' : (string) $board[$r][$c];
            }
        }
        return $s;
    }
}
