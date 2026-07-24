<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import BreakGameShell from '../../Components/BreakGameShell.vue';
import {
    isValidPlacement,
    validateSolution,
    candidatesFor,
    hint as solveHint,
    type Hint,
} from '../../lib/sodoku/solver';
import { seedForDate, deterministicId } from '../../lib/sodoku/seed';

type Difficulty = 'beginner' | 'intermediate' | 'advanced';
type Status = 'playing' | 'won' | 'lost' | 'paused';

const props = defineProps<{
    puzzle: string;          // 81 chars, '-' = empty
    solution: string;        // 81 chars, all digits
    difficulty: Difficulty;
    date: string;            // ISO date for the daily puzzle
    seed: number;            // for the URL
    difficultyOptions: Array<{ value: Difficulty; text: string; sub: string }>;
}>();

/* ------------------------------------------------------------------ *
 *  Setup: parse the puzzle + solution strings into 9×9 arrays, and
 *  restore any in-progress game from localStorage. If a saved state
 *  exists for (date, difficulty, seed), use it; otherwise start fresh.
 * ------------------------------------------------------------------ */
const SIZE = 9;

function parseBoard(s: string): number[][] {
    const board: number[][] = [];
    for (let r = 0; r < SIZE; r++) {
        const row: number[] = [];
        for (let c = 0; c < SIZE; c++) {
            const ch = s[r * SIZE + c];
            row.push(ch === '-' || ch === ' ' ? 0 : parseInt(ch, 10));
        }
        board.push(row);
    }
    return board;
}

const STORAGE_KEY = 'silo-sodoku-current';

interface SavedState {
    puzzle: string;
    board: number[][];
    given: boolean[][];
    pencil: string[][]; // string[] per cell, joined as '123' for storage
    selected: [number, number] | null;
    pencilMode: boolean;
    difficulty: Difficulty;
    date: string;
    seed: number;
    mistakes: number;
    startedAt: number;
    elapsedMs: number;
    history: string[]; // serialized board states for Undo
}

function emptyPuzzle(): SavedState {
    return {
        puzzle: props.puzzle,
        board: parseBoard(props.puzzle),
        given: parseBoard(props.puzzle).map((r) => r.map((v) => v !== 0)),
        pencil: Array.from({ length: SIZE }, () => Array(SIZE).fill('')),
        selected: null,
        pencilMode: false,
        difficulty: props.difficulty,
        date: props.date,
        seed: props.seed,
        mistakes: 0,
        startedAt: Date.now(),
        elapsedMs: 0,
        history: [],
    };
}

const state = reactive<SavedState>(emptyPuzzle());
const STATUS = ref<Status>('playing');
const MODAL_OPEN = computed(() => STATUS.value === 'won' || STATUS.value === 'lost');
const HINT = ref<Hint | null>(null);
const STORAGE_KEY_VERSION = 'v1';

// Restore saved state if it matches the current (date, difficulty, seed).
function tryRestore(): void {
    if (typeof localStorage === 'undefined') return;
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (!raw) return;
        const saved = JSON.parse(raw) as SavedState;
        if (
            saved.date !== state.date ||
            saved.difficulty !== state.difficulty ||
            saved.seed !== state.seed ||
            saved.puzzle !== state.puzzle
        ) {
            return; // different puzzle
        }
        state.board = saved.board;
        state.given = saved.given;
        state.pencil = saved.pencil;
        state.selected = saved.selected;
        state.pencilMode = saved.pencilMode;
        state.mistakes = saved.mistakes;
        state.startedAt = saved.startedAt;
        state.elapsedMs = saved.elapsedMs;
        state.history = saved.history;
        if (saved.selected) STATUS.value = 'paused';
    } catch {
        // ignore corrupted localStorage
    }
}
tryRestore();

// Persist on every change.
watch(
    state,
    () => {
        if (typeof localStorage === 'undefined') return;
        try {
            const snapshot: SavedState = { ...state };
            localStorage.setItem(STORAGE_KEY, JSON.stringify(snapshot));
        } catch {
            // localStorage may be full or disabled
        }
    },
    { deep: true }
);

/* ------------------------------------------------------------------ *
 *  Timer
 * ------------------------------------------------------------------ */
const NOW = ref(Date.now());
let timerId: ReturnType<typeof setInterval> | null = null;
onMounted(() => {
    timerId = setInterval(() => {
        NOW.value = Date.now();
    }, 1000);
});
onBeforeUnmount(() => {
    if (timerId) clearInterval(timerId);
});

const ELAPSED = computed(() => {
    if (STATUS.value === 'won' || STATUS.value === 'lost') return state.elapsedMs;
    return state.elapsedMs + (NOW.value - state.startedAt);
});

function formatElapsed(ms: number): string {
    const s = Math.floor(ms / 1000);
    const m = Math.floor(s / 60);
    return `${m.toString().padStart(2, '0')}:${(s % 60).toString().padStart(2, '0')}`;
}

/* ------------------------------------------------------------------ *
 *  Board helpers
 * ------------------------------------------------------------------ */
const SOLUTION = computed(() => parseBoard(props.solution));

const MISTAKES = computed(() => state.mistakes);
const MAX_MISTAKES = 3;

const GIVEN_CELLS = computed(() => state.given);

function isEmpty(r: number, c: number): boolean {
    return state.board[r][c] === 0 && state.given[r][c] === false;
}
function isPlayerCell(r: number, c: number): boolean {
    return state.board[r][c] !== 0 && !state.given[r][c];
}
function isWrong(r: number, c: number): boolean {
    return isPlayerCell(r, c) && state.board[r][c] !== SOLUTION.value[r][c];
}
function isSelected(r: number, c: number): boolean {
    return state.selected?.[0] === r && state.selected?.[1] === c;
}
function isRelatedToSelection(r: number, c: number): boolean {
    if (!state.selected) return false;
    const [sr, sc] = state.selected;
    if (r === sr || c === sc) return true;
    // Same 3×3 box.
    if (Math.floor(r / 3) === Math.floor(sr / 3) && Math.floor(c / 3) === Math.floor(sc / 3)) {
        return true;
    }
    return false;
}
function isSameValueAsSelection(r: number, c: number): boolean {
    if (!state.selected) return false;
    const [sr, sc] = state.selected;
    const v = state.board[sr][sc];
    return v !== 0 && state.board[r][c] === v;
}

const IS_SOLVED = computed(() =>
    state.board.every((r, ri) => r.every((v, ci) => v === SOLUTION.value[ri][ci]))
);

/* ------------------------------------------------------------------ *
 *  Interactions
 * ------------------------------------------------------------------ */
function selectCell(r: number, c: number): void {
    if (STATUS.value === 'won' || STATUS.value === 'lost') return;
    state.selected = [r, c];
    HINT.value = null;
}

function pushHistory(): void {
    state.history.push(JSON.stringify(state.board));
    if (state.history.length > 50) state.history.shift();
}

function setValue(v: number): void {
    if (!state.selected) return;
    const [r, c] = state.selected;
    if (state.given[r][c]) return;
    if (state.pencilMode) {
        // Toggle the pencil mark for this digit.
        const cur = state.pencil[r][c].split('').map(Number).filter((n) => n >= 1 && n <= 9);
        const idx = cur.indexOf(v);
        if (idx >= 0) cur.splice(idx, 1);
        else cur.push(v);
        cur.sort((a, b) => a - b);
        state.pencil[r][c] = cur.join('');
        return;
    }
    if (state.board[r][c] === v) return; // no-op
    if (!isValidPlacement(state.board, r, c, v)) {
        // Don't auto-mark wrong — user has to opt in via "Check".
    }
    pushHistory();
    state.board[r][c] = v;
    state.pencil[r][c] = '';
    HINT.value = null;
    advanceAfterMove(r, c);
}

function erase(): void {
    if (!state.selected) return;
    const [r, c] = state.selected;
    if (state.given[r][c]) return;
    if (state.board[r][c] === 0) return;
    pushHistory();
    state.board[r][c] = 0;
    state.pencil[r][c] = '';
}

function undo(): void {
    if (state.history.length === 0) return;
    const last = state.history.pop()!;
    state.board = JSON.parse(last);
    // Keep given + pencil marks stable.
    state.mistakes = Math.max(0, state.mistakes);
}

function moveSelection(dr: number, dc: number): void {
    if (!state.selected) {
        state.selected = [0, 0];
        return;
    }
    const [r, c] = state.selected;
    const nr = Math.max(0, Math.min(8, r + dr));
    const nc = Math.max(0, Math.min(8, c + dc));
    state.selected = [nr, nc];
}

function advanceAfterMove(r: number, c: number): void {
    // After a correct placement, auto-advance to the next cell.
    if (IS_SOLVED.value) {
        finishGame();
        return;
    }
    let nr = r;
    let nc = c + 1;
    if (nc > 8) {
        nr = r + 1;
        nc = 0;
    }
    if (nr > 8) return;
    state.selected = [nr, nc];
}

function finishGame(): void {
    STATUS.value = 'won';
    state.elapsedMs += NOW.value - state.startedAt;
    if (typeof localStorage !== 'undefined') {
        localStorage.removeItem(STORAGE_KEY);
    }
}

function check(): void {
    // Re-validate the current board against the solution. Mark wrong
    // cells by setting board to a negative sentinel that the template
    // checks. Use the same board but track errors separately.
    // (Implementation: increment mistakes for any cell that doesn't
    // match the solution.)
    if (STATUS.value === 'won' || STATUS.value === 'lost') return;
    let newMistakes = 0;
    for (let r = 0; r < SIZE; r++) {
        for (let c = 0; c < SIZE; c++) {
            const v = state.board[r][c];
            if (v !== 0 && !state.given[r][c] && v !== SOLUTION.value[r][c]) {
                newMistakes++;
            }
        }
    }
    if (newMistakes > state.mistakes) {
        state.mistakes = newMistakes;
    }
    if (state.mistakes >= MAX_MISTAKES) {
        STATUS.value = 'lost';
        state.elapsedMs += NOW.value - state.startedAt;
    }
}

function useHint(): void {
    if (STATUS.value === 'won' || STATUS.value === 'lost') return;
    const h = solveHint(state.board, SOLUTION.value);
    if (!h) return;
    HINT.value = h;
}

function applyHint(): void {
    if (!HINT.value) return;
    const h = HINT.value;
    if (state.given[h.row][h.col]) return;
    pushHistory();
    state.board[h.row][h.col] = h.value;
    state.pencil[h.row][h.col] = '';
    HINT.value = null;
    if (IS_SOLVED.value) {
        finishGame();
    } else {
        state.selected = [h.row, h.col];
    }
}

function newGame(d: Difficulty = state.difficulty): void {
    if (typeof localStorage !== 'undefined') {
        localStorage.removeItem(STORAGE_KEY);
    }
    // Reload the page with a new difficulty (which triggers a fresh
    // server-render with a new puzzle).
    const url = new URL(window.location.href);
    url.searchParams.set('difficulty', d);
    window.location.href = url.toString();
}

function changeDifficulty(d: Difficulty): void {
    if (d === state.difficulty) return;
    newGame(d);
}

function togglePencil(): void {
    state.pencilMode = !state.pencilMode;
}

function togglePause(): void {
    if (STATUS.value === 'won' || STATUS.value === 'lost') return;
    if (STATUS.value === 'paused') {
        STATUS.value = 'playing';
        state.startedAt = Date.now();
        NOW.value = Date.now();
    } else {
        STATUS.value = 'paused';
        state.elapsedMs += NOW.value - state.startedAt;
    }
}

/* ------------------------------------------------------------------ *
 *  Keyboard
 * ------------------------------------------------------------------ */
function onKeydown(e: KeyboardEvent) {
    if (e.metaKey || e.ctrlKey) {
        if (e.key === 'z' || e.key === 'Z') {
            e.preventDefault();
            undo();
            return;
        }
        return;
    }
    if (e.key >= '1' && e.key <= '9') {
        e.preventDefault();
        setValue(parseInt(e.key, 10));
        return;
    }
    if (e.key === '0' || e.key === 'Backspace' || e.key === 'Delete') {
        e.preventDefault();
        erase();
        return;
    }
    if (e.key === 'p' || e.key === 'P') {
        e.preventDefault();
        togglePencil();
        return;
    }
    if (e.key === 'n' || e.key === 'N') {
        e.preventDefault();
        newGame();
        return;
    }
    if (e.key === 'h' || e.key === 'H') {
        e.preventDefault();
        useHint();
        return;
    }
    if (e.key === ' ') {
        e.preventDefault();
        togglePause();
        return;
    }
    if (e.key.startsWith('Arrow')) {
        e.preventDefault();
        const map: Record<string, [number, number]> = {
            ArrowUp: [-1, 0],
            ArrowDown: [1, 0],
            ArrowLeft: [0, -1],
            ArrowRight: [0, 1],
        };
        const [dr, dc] = map[e.key]!;
        moveSelection(dr, dc);
    }
}
onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));

/* ------------------------------------------------------------------ *
 *  Computed styles: cell state is conveyed by borders, not by tinted
 *  fills. Light/dark is handled by the existing `data-bs-theme` colour
 *  mode (the theme tokens below all flip automatically).
 * ------------------------------------------------------------------ */

function cellClasses(r: number, c: number): Record<string, boolean> {
    return {
        'sodoku-cell': true,
        'is-given': state.given[r][c],
        'is-selected': isSelected(r, c),
        'is-related': isRelatedToSelection(r, c) && !isSelected(r, c),
        'is-same-value': isSameValueAsSelection(r, c) && !isSelected(r, c) && !isRelatedToSelection(r, c),
        'is-wrong': isWrong(r, c),
        'is-hint': HINT.value?.row === r && HINT.value?.col === c,
        'is-empty': isEmpty(r, c),
    };
}

function cellText(r: number, c: number): number {
    return state.board[r][c];
}

function pencilMarks(r: number, c: number): number[] {
    return state.pencil[r][c].split('').map(Number).filter((n) => n >= 1 && n <= 9);
}

const SHARE_TEXT = computed(() => {
    const mm = Math.floor(ELAPSED.value / 60000);
    const ss = Math.floor((ELAPSED.value % 60000) / 1000);
    return `Silo Sodoku · ${state.difficulty} · ${mm}:${ss.toString().padStart(2, '0')} · ${state.mistakes} mistake${state.mistakes === 1 ? '' : 's'} · ${state.date}`;
});

async function shareResult(): Promise<void> {
    if (typeof navigator === 'undefined' || !navigator.clipboard) return;
    try {
        await navigator.clipboard.writeText(SHARE_TEXT.value);
    } catch {
        // ignore clipboard failures
    }
}

const DAILY_URL = computed(() => {
    if (typeof window === 'undefined') return '';
    const url = new URL(window.location.origin + '/break/sodoku');
    url.searchParams.set('difficulty', state.difficulty);
    return url.toString();
});

function cellAriaLabel(r: number, c: number): string {
    return `Row ${r + 1}, column ${c + 1}`;
}
</script>

<template>
    <BreakGameShell title="Sodoku" icon="grid-3x3-gap-fill">
        <template #subtitle>Today's puzzle · {{ date }} · {{ difficulty }}</template>

        <template #actions>
            <div class="d-flex align-items-center gap-2">
                <div class="badge bg-body-secondary text-body border px-3 py-2">
                    <div class="small text-muted">Time</div>
                    <div class="fw-semibold fs-5">{{ formatElapsed(ELAPSED) }}</div>
                </div>
                <div class="badge bg-body-secondary text-body border px-3 py-2">
                    <div class="small text-muted">Mistakes</div>
                    <div class="fw-semibold fs-5">{{ MISTAKES }} / {{ MAX_MISTAKES }}</div>
                </div>
                <VibeButton variant="primary" class="ms-2" @click="newGame" title="New puzzle (N)">
                    <VibeIcon icon="arrow-clockwise" class="me-1" />New Game
                </VibeButton>
            </div>
        </template>

        <!-- Hint alert. -->
        <div v-if="HINT" class="alert alert-info py-2 d-flex align-items-center gap-2 small mb-3">
            <VibeIcon icon="lightbulb" />
            <span>
                <strong>{{ HINT.technique }}</strong> at row {{ HINT.row + 1 }}, col {{ HINT.col + 1 }}.
                <span v-if="HINT.technique === 'Naked Single'">Only one digit can go here.</span>
            </span>
            <VibeButton size="sm" variant="primary" class="ms-auto" @click="applyHint">Fill {{ HINT.value }}</VibeButton>
        </div>

        <!-- Board. -->
        <div class="sodoku-board-wrapper">
            <div class="sodoku-board" role="grid" :aria-label="`Sodoku ${difficulty} board`">
                <div
                    v-for="(_, boxR) in 3"
                    :key="`box-row-${boxR}`"
                    class="sodoku-box-row"
                >
                    <div
                        v-for="(_, boxC) in 3"
                        :key="`box-${boxR}-${boxC}`"
                        class="sodoku-box"
                    >
                        <div
                            v-for="(_, cellR) in 3"
                            :key="`cell-row-${boxR}-${boxC}-${cellR}`"
                            class="sodoku-cell-row"
                        >
                            <button
                                v-for="(_, cellC) in 3"
                                :key="`cell-${boxR}-${boxC}-${cellR}-${cellC}`"
                                type="button"
                                :class="cellClasses(boxR * 3 + cellR, boxC * 3 + cellC)"
                                :data-row="boxR * 3 + cellR"
                                :data-col="boxC * 3 + cellC"
                                :aria-label="cellAriaLabel(boxR * 3 + cellR, boxC * 3 + cellC)"
                                @click="selectCell(boxR * 3 + cellR, boxC * 3 + cellC)"
                            >
                                <span v-if="cellText(boxR * 3 + cellR, boxC * 3 + cellC) !== 0" class="sodoku-value">
                                    {{ cellText(boxR * 3 + cellR, boxC * 3 + cellC) }}
                                </span>
                                <span v-else-if="pencilMarks(boxR * 3 + cellR, boxC * 3 + cellC).length > 0" class="sodoku-pencils">
                                    <span v-for="p in pencilMarks(boxR * 3 + cellR, boxC * 3 + cellC)" :key="p" class="sodoku-pencil">{{ p }}</span>
                                </span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <template #message>
            <span class="text-muted small"><kbd>1-9</kbd> fill · <kbd>←↑↓→</kbd> move · <kbd>P</kbd> pencil · <kbd>H</kbd> hint · <kbd>Space</kbd> pause</span>
        </template>

        <template #extra>
            <!-- Difficulty selector. -->
            <div class="d-flex flex-wrap justify-content-center gap-2 mb-3">
                <VibeButtonGroup>
                    <VibeButton
                        v-for="opt in difficultyOptions"
                        :key="opt.value"
                        :variant="difficulty === opt.value ? 'primary' : 'secondary'"
                        :outline="difficulty !== opt.value"
                        size="sm"
                        :title="opt.sub"
                        @click="changeDifficulty(opt.value)"
                    >
                        {{ opt.text }}
                    </VibeButton>
                </VibeButtonGroup>
            </div>

            <!-- Action buttons. -->
            <div class="d-flex flex-wrap justify-content-center gap-2">
                <VibeButton size="sm" variant="secondary" outline :disabled="!state.selected" @click="erase" title="Clear (0/⌫)">Erase</VibeButton>
                <VibeButton size="sm" variant="secondary" outline :disabled="state.history.length === 0" @click="undo" title="Undo (⌘Z)">Undo</VibeButton>
                <VibeButton size="sm" :variant="state.pencilMode ? 'primary' : 'secondary'" :outline="!state.pencilMode" @click="togglePencil" :title="state.pencilMode ? 'Pencil on (P)' : 'Pencil off (P)'">Pencil</VibeButton>
                <VibeButton size="sm" variant="secondary" outline @click="useHint" title="Hint (H)">Hint</VibeButton>
                <VibeButton size="sm" variant="secondary" outline @click="check" title="Mark wrong cells">Check</VibeButton>
                <VibeButton size="sm" variant="secondary" outline @click="togglePause" title="Pause (Space)">
                    <VibeIcon :icon="STATUS === 'paused' ? 'play-fill' : 'pause-fill'" class="me-1" />
                    {{ STATUS === 'paused' ? 'Resume' : 'Pause' }}
                </VibeButton>
            </div>

            <!-- Mobile number pad. -->
            <div class="sodoku-pad d-md-none mt-3">
                <div class="d-flex flex-wrap gap-2 justify-content-center">
                    <VibeButton v-for="v in 9" :key="v" variant="light" size="lg" @click="setValue(v)">{{ v }}</VibeButton>
                </div>
            </div>
        </template>

        <!-- Win / lose overlays. -->
        <VibeModal
            v-model="MODAL_OPEN"
            :title="STATUS === 'won' ? 'Solved!' : (STATUS === 'lost' ? 'Game over' : '')"
            centered
            hide-footer
        >
            <div v-if="STATUS === 'won'" class="text-center">
                <VibeIcon icon="check2-circle" class="display-1 text-success mb-2" />
                <p class="mb-1">Solved in <strong>{{ formatElapsed(ELAPSED) }}</strong> with <strong>{{ MISTAKES }}</strong> mistake{{ MISTAKES === 1 ? '' : 's' }}.</p>
                <p class="text-muted small mb-3">Silo Sodoku · {{ difficulty }} · {{ date }}</p>
                <div class="d-flex gap-2 justify-content-center">
                    <VibeButton variant="primary" @click="shareResult">
                        <VibeIcon icon="clipboard" class="me-1" />Copy result
                    </VibeButton>
                    <VibeButton variant="secondary" outline @click="newGame">New puzzle</VibeButton>
                </div>
            </div>
            <div v-else-if="STATUS === 'lost'" class="text-center">
                <VibeIcon icon="x-circle" class="display-1 text-danger mb-2" />
                <p class="mb-1">Three mistakes. The solution is revealed.</p>
                <p class="text-muted small mb-3">Better luck next time.</p>
                <div class="d-flex gap-2 justify-content-center">
                    <VibeButton variant="primary" @click="newGame">New puzzle</VibeButton>
                </div>
            </div>
        </VibeModal>
    </BreakGameShell>
</template>

<style scoped>
.sodoku-board-wrapper {
    width: fit-content;
    margin: 0 auto;
}

/*
 * Board: nested 3×3 grids. Outer gap (between boxes) is larger than the
 * inner gap (between cells), so the 3×3 structure reads at a glance.
 */
.sodoku-board {
    display: grid;
    grid-template-rows: repeat(3, 1fr);
    gap: var(--break-tile-gap);
    padding: 0;
    background: transparent;
    width: fit-content;
    user-select: none;
}
.sodoku-box-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: var(--break-tile-gap);
}
.sodoku-box {
    display: grid;
    grid-template-rows: repeat(3, 1fr);
    gap: 0.125rem;
}
.sodoku-cell-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 0.125rem;
}

/* Cell: shared Break Room tile look. */
.sodoku-cell {
    width: var(--break-tile-size);
    height: var(--break-tile-size);
    background: var(--bs-body-bg);
    border: 2px solid var(--bs-border-color);
    border-radius: var(--bs-border-radius);
    color: var(--bs-body-color);
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: var(--bs-font-monospace);
    font-size: calc(var(--break-tile-size) * 0.55);
    font-weight: 400;
    line-height: 1;
    position: relative;
    cursor: pointer;
    transition: transform 0.12s ease, background-color 0.12s ease, box-shadow 0.12s ease;
}
.sodoku-cell:hover:not(:disabled) {
    background: var(--bs-tertiary-bg);
}
.sodoku-cell:focus-visible {
    outline: 2px solid var(--bs-primary);
    outline-offset: 2px;
}
/* Given cells: heavier weight. */
.sodoku-cell.is-given {
    font-weight: 700;
    cursor: default;
}
/* Selected: primary ring, matching Crush / DWG tiles. */
.sodoku-cell.is-selected {
    box-shadow: 0 0 0 3px var(--bs-primary);
    background: rgba(var(--bs-primary-rgb), 0.08);
    z-index: 1;
}
/* Related (same row / column / box): subtle tinted background. */
.sodoku-cell.is-related {
    background: var(--bs-tertiary-bg);
}
/* Same digit as the selected cell: slightly stronger tint. */
.sodoku-cell.is-same-value {
    background: var(--bs-secondary-bg);
}
/* Wrong (caught by Check): struck through and dimmed. */
.sodoku-cell.is-wrong {
    text-decoration: line-through;
    opacity: 0.5;
}
/* Hint: dashed primary outline. */
.sodoku-cell.is-hint {
    outline: 2px dashed var(--bs-primary);
    outline-offset: -2px;
    animation: hint-pulse 1.2s ease-in-out infinite;
}
.sodoku-value {
    line-height: 1;
}
.sodoku-pencils {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    grid-template-rows: repeat(3, 1fr);
    width: 100%;
    height: 100%;
    font-size: calc(var(--break-tile-size) * 0.18);
    color: var(--bs-secondary-color);
    line-height: 1;
    gap: 0;
}
.sodoku-pencil {
    display: flex;
    align-items: center;
    justify-content: center;
    min-width: 0;
    min-height: 0;
}
.sodoku-pad {
    width: 100%;
    max-width: 516px;
}
@keyframes hint-pulse {
    0%, 100% { outline-color: var(--bs-primary); }
    50% { outline-color: transparent; }
}
@media (prefers-reduced-motion: reduce) {
    .sodoku-cell { transition: none; }
    @keyframes hint-pulse { 0%, 100% { outline-color: var(--bs-primary); } }
}
</style>
