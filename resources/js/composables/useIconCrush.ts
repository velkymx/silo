import { ref, computed, onBeforeUnmount, nextTick } from 'vue';

export type IconName = string;

export interface Cell {
    id: number;
    icon: IconName;
}

export interface Position {
    r: number;
    c: number;
}

export type Board = (Cell | null)[][];

export const ICONS: IconName[] = [
    'folder-fill',
    'file-earmark',
    'image',
    'music-note-beamed',
    'film',
    'lock-fill',
    'star-fill',
];

export const ICON_COLORS: Record<IconName, string> = {
    'folder-fill': 'text-primary',
    'file-earmark': 'text-secondary',
    image: 'text-success',
    'music-note-beamed': 'text-warning',
    film: 'text-danger',
    'lock-fill': 'text-info',
    'star-fill': 'text-warning',
};

export const ROWS = 8;
export const COLS = 8;
export const MAX_MOVES = 30;
export const MATCH_SCORE = 10;

let idCounter = 0;

function nextId(): number {
    idCounter += 1;
    return idCounter;
}

export function createCell(icon: IconName, id?: number): Cell {
    return { id: id ?? nextId(), icon };
}

export function randomIcon(icons: IconName[] = ICONS): IconName {
    return icons[Math.floor(Math.random() * icons.length)];
}

export function createBoard(rows: number, cols: number, icons: IconName[] = ICONS): Board {
    const board: Board = Array.from({ length: rows }, () => Array.from({ length: cols }, () => null));

    for (let r = 0; r < rows; r += 1) {
        for (let c = 0; c < cols; c += 1) {
            board[r][c] = createCell(randomIconNotMatching(board, r, c, icons));
        }
    }

    return board;
}

function randomIconNotMatching(board: Board, r: number, c: number, icons: IconName[]): IconName {
    const avoid = new Set<IconName>();

    if (c >= 2 && board[r][c - 1] && board[r][c - 2] && board[r][c - 1]!.icon === board[r][c - 2]!.icon) {
        avoid.add(board[r][c - 1]!.icon);
    }
    if (r >= 2 && board[r - 1][c] && board[r - 2][c] && board[r - 1][c]!.icon === board[r - 2][c]!.icon) {
        avoid.add(board[r - 1][c]!.icon);
    }

    const pool = icons.filter((i) => !avoid.has(i));
    return pool.length ? pool[Math.floor(Math.random() * pool.length)] : icons[0];
}

export function cloneBoard(board: Board): Board {
    return board.map((row) => row.map((cell) => (cell ? { ...cell } : null)));
}

export function getMatches(board: Board): Set<string> {
    const rows = board.length;
    const cols = board[0]?.length ?? 0;
    const matches = new Set<string>();

    for (let r = 0; r < rows; r += 1) {
        let runStart = 0;
        let runIcon: IconName | null = null;
        for (let c = 0; c <= cols; c += 1) {
            const icon = c < cols ? board[r][c]?.icon ?? null : null;
            if (icon === runIcon) continue;
            if (runIcon && c - runStart >= 3) {
                for (let k = runStart; k < c; k += 1) matches.add(`${r},${k}`);
            }
            runStart = c;
            runIcon = icon;
        }
    }

    for (let c = 0; c < cols; c += 1) {
        let runStart = 0;
        let runIcon: IconName | null = null;
        for (let r = 0; r <= rows; r += 1) {
            const icon = r < rows ? board[r][c]?.icon ?? null : null;
            if (icon === runIcon) continue;
            if (runIcon && r - runStart >= 3) {
                for (let k = runStart; k < r; k += 1) matches.add(`${k},${c}`);
            }
            runStart = r;
            runIcon = icon;
        }
    }

    return matches;
}

export function swapCells(board: Board, a: Position, b: Position): void {
    const tmp = board[a.r][a.c];
    board[a.r][a.c] = board[b.r][b.c];
    board[b.r][b.c] = tmp;
}

export function clearMatches(board: Board, matches: Set<string>): void {
    matches.forEach((key) => {
        const [r, c] = key.split(',').map(Number);
        board[r][c] = null;
    });
}

export function applyGravity(board: Board, icons: IconName[] = ICONS): void {
    const rows = board.length;
    const cols = board[0]?.length ?? 0;

    for (let c = 0; c < cols; c += 1) {
        let writeRow = rows - 1;
        for (let r = rows - 1; r >= 0; r -= 1) {
            const cell = board[r][c];
            if (cell) {
                board[writeRow][c] = cell;
                if (writeRow !== r) board[r][c] = null;
                writeRow -= 1;
            }
        }
        for (let r = writeRow; r >= 0; r -= 1) {
            board[r][c] = createCell(randomIcon(icons));
        }
    }
}

export function hasPossibleMoves(board: Board): boolean {
    const rows = board.length;
    const cols = board[0]?.length ?? 0;

    for (let r = 0; r < rows; r += 1) {
        for (let c = 0; c < cols; c += 1) {
            const a = { r, c };
            if (c < cols - 1) {
                const test = cloneBoard(board);
                swapCells(test, a, { r, c: c + 1 });
                if (getMatches(test).size > 0) return true;
            }
            if (r < rows - 1) {
                const test = cloneBoard(board);
                swapCells(test, a, { r: r + 1, c });
                if (getMatches(test).size > 0) return true;
            }
        }
    }

    return false;
}

export function resolveCascades(board: Board, icons: IconName[] = ICONS, combo = 1): { score: number; maxCombo: number } {
    const matches = getMatches(board);
    if (matches.size === 0) return { score: 0, maxCombo: Math.max(1, combo - 1) };

    clearMatches(board, matches);
    applyGravity(board, icons);
    const rest = resolveCascades(board, icons, combo + 1);

    return {
        score: matches.size * MATCH_SCORE * combo + rest.score,
        maxCombo: Math.max(combo, rest.maxCombo),
    };
}

export function areAdjacent(a: Position, b: Position): boolean {
    return Math.abs(a.r - b.r) + Math.abs(a.c - b.c) === 1;
}

function loadBestScore(): number {
    if (typeof window === 'undefined') return 0;
    const raw = window.localStorage.getItem('iconCrushBest');
    return raw ? Number(raw) : 0;
}

function saveBestScore(score: number): void {
    if (typeof window === 'undefined') return;
    window.localStorage.setItem('iconCrushBest', String(score));
}

export interface IconCrushHooks {
    beforeBoardUpdate?: () => void;
    afterBoardUpdate?: () => void;
}

export function useIconCrush(icons: IconName[] = ICONS, hooks: IconCrushHooks = {}) {
    const board = ref<Board>(createBoard(ROWS, COLS, icons));
    const score = ref(0);
    const moves = ref(MAX_MOVES);
    const selected = ref<Position | null>(null);
    const cursor = ref<Position>({ r: 0, c: 0 });
    const status = ref<'playing' | 'gameover'>('playing');
    const resolving = ref(false);
    const clearingIds = ref<Set<number>>(new Set());
    const message = ref('');
    const bestScore = ref(loadBestScore());

    let resolveTimer: ReturnType<typeof setTimeout> | null = null;

    onBeforeUnmount(() => {
        if (resolveTimer) clearTimeout(resolveTimer);
    });

    const isGameOver = computed(() => status.value === 'gameover');

    function setBoard(next: Board): void {
        board.value = next;
    }

    function shuffleIfStuck(): boolean {
        if (hasPossibleMoves(board.value)) return false;
        let attempts = 0;
        do {
            board.value = createBoard(ROWS, COLS, icons);
            attempts += 1;
        } while (!hasPossibleMoves(board.value) && attempts < 10);
        message.value = 'No moves left — board shuffled!';
        return true;
    }

    function newGame(): void {
        score.value = 0;
        moves.value = MAX_MOVES;
        status.value = 'playing';
        selected.value = null;
        message.value = '';
        resolving.value = false;
        clearingIds.value = new Set();
        board.value = createBoard(ROWS, COLS, icons);
        shuffleIfStuck();
    }

    function recordScore(points: number): void {
        score.value += points;
        if (score.value > bestScore.value) {
            bestScore.value = score.value;
            saveBestScore(bestScore.value);
        }
    }

    function finishTurn(): void {
        if (moves.value <= 0 && !resolving.value) {
            status.value = 'gameover';
            message.value = 'Game over! Great run.';
        }
    }

    function wait(ms: number): Promise<void> {
        return new Promise((resolve) => {
            resolveTimer = setTimeout(() => {
                resolveTimer = null;
                resolve();
            }, ms);
        });
    }

    async function resolveAnimated(nextBoard: Board): Promise<number> {
        resolving.value = true;
        let total = 0;
        let combo = 1;

        while (true) {
            const matches = getMatches(nextBoard);
            if (matches.size === 0) break;

            clearingIds.value = new Set(
                [...matches].map((key) => {
                    const [r, c] = key.split(',').map(Number);
                    return nextBoard[r]![c]!.id;
                })
            );

            // Let the matched cells scale/fade out before the board updates.
            await wait(180);

            clearMatches(nextBoard, matches);
            applyGravity(nextBoard, icons);
            total += matches.size * MATCH_SCORE * combo;
            combo += 1;

            hooks.beforeBoardUpdate?.();
            board.value = cloneBoard(nextBoard);
            await nextTick();
            hooks.afterBoardUpdate?.();
            clearingIds.value = new Set();

            // Let the slide animation finish before resolving the next cascade.
            await wait(260);
        }

        resolving.value = false;
        return total;
    }

    async function attemptSwap(a: Position, b: Position): Promise<void> {
        if (resolving.value || isGameOver.value) return;

        const test = cloneBoard(board.value);
        swapCells(test, a, b);
        const matches = getMatches(test);

        if (matches.size === 0) {
            message.value = 'No match — try another swap.';
            selected.value = null;
            return;
        }

        selected.value = null;
        moves.value -= 1;
        message.value = '';

        const points = await resolveAnimated(test);
        recordScore(points);
        shuffleIfStuck();
        finishTurn();
    }

    function select(pos: Position): void {
        if (resolving.value || isGameOver.value) return;

        cursor.value = pos;

        if (!selected.value) {
            selected.value = pos;
            message.value = '';
            return;
        }

        if (selected.value.r === pos.r && selected.value.c === pos.c) {
            selected.value = null;
            return;
        }

        if (!areAdjacent(selected.value, pos)) {
            selected.value = pos;
            return;
        }

        attemptSwap(selected.value, pos);
    }

    function moveCursor(deltaR: number, deltaC: number): void {
        const r = Math.max(0, Math.min(ROWS - 1, cursor.value.r + deltaR));
        const c = Math.max(0, Math.min(COLS - 1, cursor.value.c + deltaC));
        cursor.value = { r, c };
        const el = document.getElementById(`crush-cell-${r}-${c}`);
        el?.focus();
    }

    function handleKey(e: KeyboardEvent): void {
        if (isGameOver.value) return;

        switch (e.key) {
            case 'ArrowUp':
                e.preventDefault();
                moveCursor(-1, 0);
                break;
            case 'ArrowDown':
                e.preventDefault();
                moveCursor(1, 0);
                break;
            case 'ArrowLeft':
                e.preventDefault();
                moveCursor(0, -1);
                break;
            case 'ArrowRight':
                e.preventDefault();
                moveCursor(0, 1);
                break;
            case 'Enter':
            case ' ':
                e.preventDefault();
                select(cursor.value);
                break;
            case 'Escape':
                selected.value = null;
                break;
        }
    }

    return {
        board,
        score,
        moves,
        selected,
        cursor,
        status,
        resolving,
        clearingIds,
        message,
        bestScore,
        isGameOver,
        newGame,
        select,
        handleKey,
    };
}
