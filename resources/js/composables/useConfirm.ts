import { reactive } from 'vue';

type DialogMode = 'confirm' | 'prompt';

interface DialogState {
    open: boolean;
    mode: DialogMode;
    title: string;
    message: string;
    confirmLabel: string;
    cancelLabel: string;
    variant: string;
    inputValue: string;
    placeholder: string;
}

interface ConfirmOptions {
    message: string;
    title?: string;
    confirmLabel?: string;
    cancelLabel?: string;
    variant?: string;
}

interface PromptOptions extends ConfirmOptions {
    value?: string;
    placeholder?: string;
}

type DialogValue = boolean | string | null;

interface QueueItem {
    mode: DialogMode;
    options: ConfirmOptions | PromptOptions;
    resolve: (value: DialogValue) => void;
}

// Module-level singleton: every component that calls confirm()/prompt() drives
// the same state, and a single <VibeModal> host (the shared DialogHost component) renders
// it. Replaces the unstyleable native window.confirm/window.prompt.
const state = reactive<DialogState>({
    open: false,
    mode: 'confirm',
    title: '',
    message: '',
    confirmLabel: 'Confirm',
    cancelLabel: 'Cancel',
    variant: 'primary',
    inputValue: '',
    placeholder: '',
});

// FIFO queue + the resolver for the dialog currently on screen. A second
// confirm()/prompt() raised before the first settles is queued instead of
// clobbering the in-flight resolver (which would hang the first caller forever).
const queue: QueueItem[] = [];
let currentResolver: ((value: DialogValue) => void) | null = null;

function openNext(): void {
    if (state.open || queue.length === 0) {
        return;
    }
    const next = queue.shift()!;
    currentResolver = next.resolve;
    state.mode = next.mode;
    state.title = next.options.title ?? (next.mode === 'prompt' ? 'Enter a value' : 'Please confirm');
    state.message = next.options.message;
    state.confirmLabel = next.options.confirmLabel ?? (next.mode === 'prompt' ? 'OK' : 'Confirm');
    state.cancelLabel = next.options.cancelLabel ?? 'Cancel';
    state.variant = next.options.variant ?? 'primary';
    state.inputValue = next.mode === 'prompt' ? ((next.options as PromptOptions).value ?? '') : '';
    state.placeholder = next.mode === 'prompt' ? ((next.options as PromptOptions).placeholder ?? '') : '';
    state.open = true;
}

function enqueue(mode: DialogMode, options: ConfirmOptions | PromptOptions): Promise<DialogValue> {
    return new Promise<DialogValue>((resolve) => {
        queue.push({ mode, options, resolve });
        openNext();
    });
}

function settle(value: DialogValue): void {
    const fn = currentResolver;
    currentResolver = null;
    state.open = false;
    fn?.(value);
    // Let the modal close transition begin before showing the next dialog.
    setTimeout(openNext, 0);
}

function confirm(options: ConfirmOptions): Promise<boolean> {
    return enqueue('confirm', options) as Promise<boolean>;
}

function prompt(options: PromptOptions): Promise<string | null> {
    return enqueue('prompt', options) as Promise<string | null>;
}

function accept(): void {
    settle(state.mode === 'prompt' ? state.inputValue : true);
}

function cancel(): void {
    settle(state.mode === 'prompt' ? null : false);
}

export function useConfirm() {
    return { confirm };
}

export function usePrompt() {
    return { prompt };
}

/** Used by the single dialog host (AppLayout) to render + resolve the dialog. */
export function useDialogHost() {
    return { state, accept, cancel };
}
