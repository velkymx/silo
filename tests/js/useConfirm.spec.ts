import { describe, it, expect } from 'vitest';
import { useConfirm, usePrompt, useDialogHost } from '@/composables/useConfirm';

describe('useConfirm / usePrompt', () => {
    it('confirm resolves true on accept', async () => {
        const { confirm } = useConfirm();
        const { state, accept } = useDialogHost();
        const p = confirm({ message: 'Delete it?' });
        expect(state.open).toBe(true);
        expect(state.mode).toBe('confirm');
        expect(state.message).toBe('Delete it?');
        accept();
        await expect(p).resolves.toBe(true);
        expect(state.open).toBe(false);
    });

    it('confirm resolves false on cancel', async () => {
        const { confirm } = useConfirm();
        const { state, cancel } = useDialogHost();
        const p = confirm({ message: 'Delete it?' });
        expect(state.open).toBe(true);
        cancel();
        await expect(p).resolves.toBe(false);
    });

    it('prompt resolves the typed value on accept', async () => {
        const { prompt } = usePrompt();
        const { state, accept } = useDialogHost();
        const p = prompt({ message: 'Name?', value: 'seed' });
        expect(state.mode).toBe('prompt');
        expect(state.inputValue).toBe('seed');
        state.inputValue = 'My Folder';
        accept();
        await expect(p).resolves.toBe('My Folder');
    });

    it('prompt resolves null on cancel', async () => {
        const { prompt } = usePrompt();
        const { cancel } = useDialogHost();
        const p = prompt({ message: 'Name?' });
        cancel();
        await expect(p).resolves.toBeNull();
    });

    it('queues a second dialog instead of clobbering the first resolver', async () => {
        const { confirm } = useConfirm();
        const { state, accept } = useDialogHost();

        const p1 = confirm({ message: 'First?' });
        const p2 = confirm({ message: 'Second?' });

        // Only the first is shown; the second is queued.
        expect(state.open).toBe(true);
        expect(state.message).toBe('First?');

        accept();
        await expect(p1).resolves.toBe(true);

        // The queued dialog opens next.
        await new Promise((r) => setTimeout(r, 0));
        expect(state.open).toBe(true);
        expect(state.message).toBe('Second?');

        accept();
        await expect(p2).resolves.toBe(true);
    });
});
