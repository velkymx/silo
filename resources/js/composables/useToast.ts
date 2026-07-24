import { reactive } from 'vue';

export interface Toast {
    id: number;
    text: string;
    variant: 'success' | 'danger' | 'info';
    undo?: () => void;
}

const state = reactive<{ items: Toast[] }>({ items: [] });
let seq = 0;

function dismiss(id: number): void {
    state.items = state.items.filter((t) => t.id !== id);
}

function push(text: string, opts: { variant?: Toast['variant']; undo?: () => void; ttl?: number } = {}): number {
    const id = ++seq;
    state.items.push({ id, text, variant: opts.variant ?? 'success', undo: opts.undo });
    setTimeout(() => dismiss(id), opts.ttl ?? 6000);
    return id;
}

export function useToast() {
    return { state, push, dismiss };
}
