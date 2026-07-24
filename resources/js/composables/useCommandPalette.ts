import { reactive } from 'vue';

const state = reactive({ open: false });

function open() { state.open = true; }
function close() { state.open = false; }
function toggle() { state.open = !state.open; }

export function useCommandPalette() {
    return { state, open, close, toggle };
}
