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

// Module-level singleton: every component that calls confirm()/prompt() drives
// the same state, and a single <VibeModal> host (mounted in AppLayout) renders
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

let resolver: ((value: boolean | string | null) => void) | null = null;

function settle(value: boolean | string | null): void {
    const fn = resolver;
    resolver = null;
    state.open = false;
    fn?.(value);
}

function confirm(options: ConfirmOptions): Promise<boolean> {
    return new Promise<boolean>((resolve) => {
        resolver = resolve as (value: boolean | string | null) => void;
        state.mode = 'confirm';
        state.title = options.title ?? 'Please confirm';
        state.message = options.message;
        state.confirmLabel = options.confirmLabel ?? 'Confirm';
        state.cancelLabel = options.cancelLabel ?? 'Cancel';
        state.variant = options.variant ?? 'primary';
        state.inputValue = '';
        state.placeholder = '';
        state.open = true;
    });
}

function prompt(options: PromptOptions): Promise<string | null> {
    return new Promise<string | null>((resolve) => {
        resolver = resolve as (value: boolean | string | null) => void;
        state.mode = 'prompt';
        state.title = options.title ?? 'Enter a value';
        state.message = options.message;
        state.confirmLabel = options.confirmLabel ?? 'OK';
        state.cancelLabel = options.cancelLabel ?? 'Cancel';
        state.variant = options.variant ?? 'primary';
        state.inputValue = options.value ?? '';
        state.placeholder = options.placeholder ?? '';
        state.open = true;
    });
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
