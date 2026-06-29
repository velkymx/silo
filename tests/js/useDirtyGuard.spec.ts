import { describe, it, expect, beforeEach, vi } from 'vitest';

const confirmMock = vi.hoisted(() => vi.fn());

vi.mock('@/composables/useConfirm', () => ({
    useConfirm: () => ({ confirm: confirmMock }),
}));

import { useDirtyGuard } from '@/composables/useDirtyGuard';

describe('useDirtyGuard (FE-P1-53)', () => {
    beforeEach(() => {
        confirmMock.mockReset();
    });

    it('calls close() immediately when not dirty', async () => {
        const close = vi.fn();
        const { guardedClose } = useDirtyGuard(() => false);
        await guardedClose(close);
        expect(confirmMock).not.toHaveBeenCalled();
        expect(close).toHaveBeenCalledOnce();
    });

    it('prompts before closing when dirty and user confirms', async () => {
        confirmMock.mockResolvedValueOnce(true);
        const close = vi.fn();
        const { guardedClose } = useDirtyGuard(() => true);
        await guardedClose(close);
        expect(confirmMock).toHaveBeenCalledWith(expect.objectContaining({
            title: 'Discard changes?',
            confirmLabel: 'Discard',
            variant: 'danger',
        }));
        expect(close).toHaveBeenCalledOnce();
    });

    it('does not close when dirty and user cancels', async () => {
        confirmMock.mockResolvedValueOnce(false);
        const close = vi.fn();
        const { guardedClose } = useDirtyGuard(() => true);
        await guardedClose(close);
        expect(close).not.toHaveBeenCalled();
    });

    it('re-evaluates isDirty on each invocation', async () => {
        const isDirty = vi.fn().mockReturnValueOnce(true).mockReturnValueOnce(false);
        const close = vi.fn();
        const { guardedClose } = useDirtyGuard(isDirty);
        await guardedClose(close);
        expect(confirmMock).toHaveBeenCalledOnce();
        await guardedClose(close);
        expect(close).toHaveBeenCalledOnce(); // only from the first call after user confirm
    });
});
