import { useConfirm } from './useConfirm';

/**
 * Intercepts a close/discard action when there are unsaved changes.
 * `isDirty` — reactive getter returning true when edits are pending.
 * `guardedClose(close)` — prompts the user before calling `close()`.
 */
export function useDirtyGuard(isDirty: () => boolean) {
    const { confirm } = useConfirm();

    async function guardedClose(close: () => void): Promise<void> {
        if (isDirty()) {
            const ok = await confirm({
                title: 'Discard changes?',
                message: 'You have unsaved changes. Discard them?',
                confirmLabel: 'Discard',
                variant: 'danger',
            });
            if (!ok) return;
        }
        close();
    }

    return { guardedClose };
}
