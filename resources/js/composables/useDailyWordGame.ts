import { ref, computed } from 'vue';
import { http } from '@/lib/http';

export type LetterStatus = 'correct' | 'present' | 'absent' | null;

export interface DailyWordGameState {
    wordLength: number;
    maxGuesses: number;
    guesses: string[];
    statuses: LetterStatus[][];
    gameOver: boolean;
    won: boolean;
    target: string | null;
}

export function useDailyWordGame(initial: DailyWordGameState) {
    const wordLength = ref(initial.wordLength);
    const maxGuesses = ref(initial.maxGuesses);
    const guesses = ref<string[]>(initial.guesses);
    const statuses = ref<LetterStatus[][]>(initial.statuses);
    const gameOver = ref(initial.gameOver);
    const won = ref(initial.won);
    const target = ref<string | null>(initial.target);
    const current = ref('');
    const message = ref('');

    const keyboardStatus = computed<Record<string, LetterStatus>>(() => {
        const map: Record<string, LetterStatus> = {};

        for (let i = 0; i < guesses.value.length; i += 1) {
            const word = guesses.value[i];
            const row = statuses.value[i];
            if (!word || !row) continue;

            for (let j = 0; j < word.length; j += 1) {
                const letter = word[j].toUpperCase();
                const status = row[j];

                if (status === 'correct') {
                    map[letter] = 'correct';
                } else if (status === 'present' && map[letter] !== 'correct') {
                    map[letter] = 'present';
                } else if (status === 'absent' && !map[letter]) {
                    map[letter] = 'absent';
                }
            }
        }

        return map;
    });

    function addLetter(letter: string): void {
        if (gameOver.value) return;
        if (current.value.length < wordLength.value && /^[a-zA-Z]$/.test(letter)) {
            current.value += letter.toLowerCase();
            message.value = '';
        }
    }

    function removeLetter(): void {
        if (gameOver.value) return;
        current.value = current.value.slice(0, -1);
        message.value = '';
    }

    async function submit(): Promise<void> {
        if (gameOver.value) return;

        if (current.value.length !== wordLength.value) {
            message.value = `Need ${wordLength.value} letters`;
            return;
        }

        try {
            const res = await http.post<{
                statuses: LetterStatus[];
                guess: string;
                gameOver: boolean;
                won: boolean;
                target: string | null;
            }>('/break/dwg/guess', { word: current.value });

            guesses.value.push(res.guess);
            statuses.value.push(res.statuses);
            gameOver.value = res.gameOver;
            won.value = res.won;
            target.value = res.target;
            current.value = '';
            message.value = '';
        } catch (e: any) {
            message.value = e?.data?.message ?? 'Invalid guess';
        }
    }

    function handleKey(e: KeyboardEvent): void {
        if (e.key === 'Enter') {
            e.preventDefault();
            submit();
        } else if (e.key === 'Backspace') {
            e.preventDefault();
            removeLetter();
        } else if (e.key.length === 1 && /^[a-zA-Z]$/.test(e.key)) {
            e.preventDefault();
            addLetter(e.key);
        }
    }

    return {
        wordLength,
        maxGuesses,
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
    };
}
