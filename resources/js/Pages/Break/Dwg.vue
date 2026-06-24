<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue';
import BreakGameShell from '../../Components/BreakGameShell.vue';
import { useDailyWordGame } from '../../composables/useDailyWordGame';
import type { LetterStatus } from '../../composables/useDailyWordGame';

const props = defineProps<{
    date: string;
    wordLength: number;
    maxGuesses: number;
    guesses: string[];
    statuses: LetterStatus[][];
    gameOver: boolean;
    won: boolean;
    target: string | null;
}>();

const {
    guesses,
    statuses,
    gameOver,
    won,
    target,
    current,
    message,
    keyboardStatus,
    addLetter,
    removeLetter,
    submit,
    handleKey,
} = useDailyWordGame({
    wordLength: props.wordLength,
    maxGuesses: props.maxGuesses,
    guesses: props.guesses,
    statuses: props.statuses,
    gameOver: props.gameOver,
    won: props.won,
    target: props.target,
});

const boardEl = ref<HTMLElement>();

const keyboardRows = [
    ['Q', 'W', 'E', 'R', 'T', 'Y', 'U', 'I', 'O', 'P'],
    ['A', 'S', 'D', 'F', 'G', 'H', 'J', 'K', 'L'],
    ['Enter', 'Z', 'X', 'C', 'V', 'B', 'N', 'M', 'Backspace'],
];

function statusClass(status: LetterStatus): string {
    switch (status) {
        case 'correct':
            return 'dwg-tile-correct';
        case 'present':
            return 'dwg-tile-present';
        case 'absent':
            return 'dwg-tile-absent';
        default:
            return '';
    }
}

function keyClass(letter: string): string {
    const status = keyboardStatus.value[letter];
    switch (status) {
        case 'correct':
            return 'dwg-key-correct';
        case 'present':
            return 'dwg-key-present';
        case 'absent':
            return 'dwg-key-absent';
        default:
            return '';
    }
}

function clickKey(key: string): void {
    if (key === 'Enter') {
        submit();
    } else if (key === 'Backspace') {
        removeLetter();
    } else {
        addLetter(key);
    }
}

function tileLabel(row: number, col: number): string {
    if (row < guesses.value.length) {
        const letter = guesses.value[row][col] ?? '';
        const status = statuses.value[row]?.[col] ?? 'empty';
        return `${letter.toUpperCase()} ${status}`;
    }
    if (row === guesses.value.length && current.value[col]) {
        return current.value[col].toUpperCase();
    }
    return 'empty';
}

onMounted(() => {
    window.addEventListener('keydown', handleKey);
    boardEl.value?.focus();
});

onBeforeUnmount(() => {
    window.removeEventListener('keydown', handleKey);
});
</script>

<template>
    <BreakGameShell title="Daily Word Game" icon="type">
        <template #subtitle>{{ date }}</template>

        <template #actions>
            <VibeButton variant="primary" href="/break/crush">
                <VibeIcon icon="joystick" class="me-1" />Crush
            </VibeButton>
        </template>

        <div class="alert alert-info small py-2 px-3 mb-3">
            <div class="fw-semibold mb-1">How to play</div>
            <p class="mb-2">Guess the 5-letter word in 6 tries. Use the keyboard to type, then press Enter.</p>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="dwg-mini-tile dwg-tile-correct">W</span>
                <span>Right letter, right spot.</span>
            </div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="dwg-mini-tile dwg-tile-present">I</span>
                <span>Right letter, wrong spot.</span>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="dwg-mini-tile dwg-tile-absent">L</span>
                <span>Letter is not in the word.</span>
            </div>
        </div>

        <div
            class="dwg-play-area"
            :style="{ '--dwg-cols': wordLength }"
        >
            <div
                ref="boardEl"
                role="application"
                aria-label="Daily word game board"
                tabindex="0"
                class="dwg-board"
            >
                <div class="dwg-grid" @click="boardEl?.focus()">
                    <template v-for="row in maxGuesses" :key="`row-${row}`">
                        <div
                            v-for="col in wordLength"
                            :key="`row-${row}-col-${col}`"
                            class="dwg-tile"
                            :class="{
                                [statusClass(statuses[row - 1]?.[col - 1] ?? null)]: true,
                                filled: row - 1 < guesses.length || (row - 1 === guesses.length && current[col - 1]),
                            }"
                            :aria-label="tileLabel(row - 1, col - 1)"
                        >
                            <span v-if="row - 1 < guesses.length" class="dwg-letter">
                                {{ guesses[row - 1][col - 1] }}
                            </span>
                            <span v-else-if="row - 1 === guesses.length && current[col - 1]" class="dwg-letter">
                                {{ current[col - 1] }}
                            </span>
                        </div>
                    </template>
                </div>
            </div>

            <div class="dwg-keyboard">
                <div v-for="(row, r) in keyboardRows" :key="`kb-row-${r}`" class="dwg-keyboard-row">
                    <button
                        v-for="key in row"
                        :key="key"
                        type="button"
                        class="dwg-key"
                        :class="[key.length > 1 ? 'dwg-key-wide' : '', keyClass(key)]"
                        @click="clickKey(key)"
                    >
                        <VibeIcon v-if="key === 'Backspace'" icon="backspace" />
                        <span v-else>{{ key }}</span>
                    </button>
                </div>
            </div>
        </div>

        <template #message>
            <div v-if="message" class="alert alert-danger py-2 px-3 small mb-0">{{ message }}</div>
            <div v-else-if="gameOver && won" class="alert alert-success py-2 px-3 small mb-0">You got it!</div>
            <div v-else-if="gameOver" class="alert alert-secondary py-2 px-3 small mb-0">
                The word was <strong class="text-uppercase">{{ target }}</strong>.
            </div>
            <div v-else class="small text-muted">Type any {{ wordLength }}-letter word and press Enter.</div>
        </template>
    </BreakGameShell>
</template>

<style scoped>
.dwg-play-area {
    --dwg-board-width: calc(var(--dwg-cols) * var(--break-tile-size) + (var(--dwg-cols) - 1) * var(--break-tile-gap));
    width: fit-content;
    margin: 0 auto;
}

.dwg-board {
    outline: none;
}

.dwg-board:focus-visible {
    box-shadow: 0 0 0 3px var(--bs-primary);
    border-radius: var(--bs-border-radius);
}

.dwg-grid {
    display: grid;
    grid-template-columns: repeat(var(--dwg-cols), var(--break-tile-size));
    gap: var(--break-tile-gap);
    width: var(--dwg-board-width);
}

.dwg-tile {
    aspect-ratio: 1 / 1;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid var(--bs-border-color);
    border-radius: var(--bs-border-radius);
    background: var(--bs-body-bg);
    color: var(--bs-body-color);
    font-size: calc(var(--break-tile-size) * 0.55);
    font-weight: 700;
    text-transform: uppercase;
    transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease;
}

.dwg-tile.filled {
    border-color: var(--bs-secondary);
}

.dwg-tile-correct {
    background: var(--bs-success) !important;
    border-color: var(--bs-success) !important;
    color: #fff !important;
}

.dwg-tile-present {
    background: var(--bs-warning) !important;
    border-color: var(--bs-warning) !important;
    color: #000 !important;
}

.dwg-tile-absent {
    background: var(--bs-secondary) !important;
    border-color: var(--bs-secondary) !important;
    color: #fff !important;
}

.dwg-mini-tile {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1.75rem;
    height: 1.75rem;
    border-radius: var(--bs-border-radius);
    font-weight: 700;
    text-transform: uppercase;
    flex-shrink: 0;
}

.dwg-letter {
    line-height: 1;
}

.dwg-keyboard {
    display: flex;
    flex-direction: column;
    gap: var(--break-tile-gap);
    width: var(--dwg-board-width);
    margin: 1rem auto 0;
}

.dwg-keyboard-row {
    display: flex;
    justify-content: center;
    gap: 0.375rem;
}

.dwg-key {
    flex: 1;
    min-width: 1.75rem;
    max-width: 2.75rem;
    height: calc(var(--break-tile-size) * 0.75);
    border: 0;
    border-radius: var(--bs-border-radius);
    background: var(--bs-tertiary-bg);
    color: var(--bs-body-color);
    font-weight: 600;
    text-transform: uppercase;
    cursor: pointer;
    transition: background-color 0.15s ease, transform 0.05s ease;
    padding: 0;
}

.dwg-key:hover:not(:disabled) {
    background: var(--bs-secondary-bg);
}

.dwg-key:active {
    transform: scale(0.96);
}

.dwg-key-wide {
    flex: 1.5;
    max-width: 4.5rem;
    font-size: 0.75rem;
}

.dwg-key-correct {
    background: var(--bs-success) !important;
    color: #fff !important;
}

.dwg-key-present {
    background: var(--bs-warning) !important;
    color: #000 !important;
}

.dwg-key-absent {
    background: var(--bs-secondary) !important;
    color: #fff !important;
}

@media (max-width: 400px) {
    .dwg-grid {
        gap: 0.25rem;
    }

    .dwg-keyboard-row {
        gap: 0.25rem;
    }

    .dwg-key {
        height: 2.75rem;
        font-size: 0.8rem;
    }
}
</style>
