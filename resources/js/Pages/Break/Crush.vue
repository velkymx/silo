<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import BreakGameShell from '../../Components/BreakGameShell.vue';
import { useIconCrush, ICONS, ICON_COLORS, ROWS, COLS } from '../../composables/useIconCrush';
import type { Cell } from '../../composables/useIconCrush';

const boardEl = ref<HTMLElement>();
const cellRects = new Map<number, DOMRect>();

function capturePositions(): void {
    boardEl.value?.querySelectorAll<HTMLElement>('.crush-cell[data-id]').forEach((el) => {
        const id = Number(el.getAttribute('data-id'));
        cellRects.set(id, el.getBoundingClientRect());
    });
}

function animateSlides(): void {
    boardEl.value?.querySelectorAll<HTMLElement>('.crush-cell[data-id]').forEach((el) => {
        const id = Number(el.getAttribute('data-id'));
        const oldRect = cellRects.get(id);
        if (!oldRect) return;

        const newRect = el.getBoundingClientRect();
        const dx = oldRect.left - newRect.left;
        const dy = oldRect.top - newRect.top;
        if (dx === 0 && dy === 0) return;

        el.style.transition = 'none';
        el.style.transform = `translate(${dx}px, ${dy}px)`;
        void el.offsetHeight;
        el.style.transition = 'transform 0.25s ease';
        el.style.transform = '';

        const cleanup = () => {
            el.style.transition = '';
            el.style.transform = '';
            el.removeEventListener('transitionend', cleanup);
        };
        el.addEventListener('transitionend', cleanup);
    });
    cellRects.clear();
}

const {
    board,
    score,
    moves,
    selected,
    clearingIds,
    message,
    bestScore,
    isGameOver,
    newGame,
    select,
    handleKey,
} = useIconCrush(ICONS, {
    beforeBoardUpdate: capturePositions,
    afterBoardUpdate: animateSlides,
});

const columns = computed<(Cell | null)[][]>(() =>
    Array.from({ length: COLS }, (_, c) =>
        Array.from({ length: ROWS }, (_, r) => board.value[r]![c]!)
    )
);

onMounted(() => {
    boardEl.value?.focus();
});
</script>

<template>
    <BreakGameShell title="Icon Crush" icon="grid-3x3-gap-fill">
        <template #subtitle>
            Click or tap two adjacent icons to swap. Use arrow keys + Enter to play.
        </template>

        <template #actions>
            <div class="d-flex align-items-center gap-2">
                <div class="badge bg-body-secondary text-body border px-3 py-2">
                    <div class="small text-muted">Score</div>
                    <div class="fw-semibold fs-5">{{ score }}</div>
                </div>
                <div class="badge bg-body-secondary text-body border px-3 py-2">
                    <div class="small text-muted">Moves</div>
                    <div class="fw-semibold fs-5">{{ moves }}</div>
                </div>
                <div class="badge bg-body-secondary text-body border px-3 py-2">
                    <div class="small text-muted">Best</div>
                    <div class="fw-semibold fs-5">{{ bestScore }}</div>
                </div>
                <AppButton variant="primary" class="ms-2" @click="newGame">
                    <VibeIcon icon="arrow-clockwise" class="me-1" />New Game
                </AppButton>
            </div>
        </template>

        <div
            ref="boardEl"
            role="application"
            aria-label="Icon Crush game board"
            tabindex="0"
            class="crush-board"
            @keydown="handleKey"
        >
            <div class="crush-grid">
                <div
                    v-for="(column, c) in columns"
                    :key="`col-${c}`"
                    class="crush-column"
                >
                    <button
                        v-for="(cell, r) in column"
                        :id="`crush-cell-${r}-${c}`"
                        :key="cell ? cell.id : `empty-${r}-${c}`"
                        type="button"
                        class="crush-cell"
                        :class="{
                            selected: selected && selected.r === r && selected.c === c,
                            clearing: cell && clearingIds.has(cell.id),
                            disabled: isGameOver,
                            'crush-cell-empty': !cell,
                        }"
                        :data-id="cell ? cell.id : undefined"
                        :aria-label="cell ? `${cell.icon} icon at row ${r + 1} column ${c + 1}` : 'empty cell'"
                        :disabled="isGameOver || !cell"
                        @click="select({ r, c })"
                    >
                        <VibeIcon
                            v-if="cell"
                            :icon="cell.icon"
                            class="crush-icon"
                            :class="ICON_COLORS[cell.icon]"
                            aria-hidden="true"
                        />
                    </button>
                </div>
            </div>
        </div>

        <template #message>
            <div v-show="message" class="small" :class="isGameOver ? 'text-danger fw-semibold' : 'text-muted'">
                {{ message }}
            </div>
            <div v-show="!message" class="small text-muted" aria-hidden="true">&nbsp;</div>
        </template>
    </BreakGameShell>
</template>

<style scoped>
.crush-board {
    outline: none;
    width: fit-content;
    margin: 0 auto;
}

.crush-board:focus-visible {
    box-shadow: 0 0 0 3px var(--bs-primary);
}

.crush-grid {
    display: grid;
    grid-template-columns: repeat(8, var(--break-tile-size));
    gap: var(--break-tile-gap);
    position: relative;
}

.crush-column {
    display: grid;
    grid-template-rows: repeat(8, var(--break-tile-size));
    gap: var(--break-tile-gap);
    position: relative;
}

.crush-cell {
    aspect-ratio: 1 / 1;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid var(--bs-border-color);
    border-radius: var(--bs-border-radius);
    background: var(--bs-body-bg);
    color: var(--bs-body-color);
    cursor: pointer;
    transition: transform 0.12s ease, background-color 0.12s ease, box-shadow 0.12s ease;
    padding: 0;
}

.crush-cell:hover:not(:disabled) {
    background: var(--bs-tertiary-bg);
}

.crush-cell:focus-visible {
    outline: 2px solid var(--bs-primary);
    outline-offset: 2px;
}

.crush-cell.selected {
    box-shadow: 0 0 0 3px var(--bs-primary);
    background: rgba(var(--bs-primary-rgb), 0.08);
}

.crush-cell.clearing {
    transform: scale(0.4);
    opacity: 0.3;
}

.crush-cell:disabled {
    cursor: default;
}

.crush-cell-empty {
    background: transparent;
    border-color: transparent;
    pointer-events: none;
}

.crush-icon {
    font-size: clamp(1rem, 3.5vw, 1.5rem);
    pointer-events: none;
}
</style>
