import { config } from '@vue/test-utils';

// Lightweight stand-ins for globally-registered VibeUI components so component
// tests can mount real app components without booting the whole VibeUI plugin.
// Slot-bearing stubs forward their default slot (with the scope the component
// expects) so child content still renders.
const slotStub = (name: string, slotProps: Record<string, unknown> = {}) => ({
    name,
    setup(_: unknown, { slots }: { slots: Record<string, ((p?: unknown) => unknown) | undefined> }) {
        return () => (slots.default ? slots.default(slotProps) : null);
    },
});

config.global.components = {
    VibeIcon: { name: 'VibeIcon', template: '<i class="bi"></i>' },
    VibeSpinner: { name: 'VibeSpinner', template: '<span class="spinner"></span>' },
    VibeDroppable: slotStub('VibeDroppable', { isOver: false }),
    VibeDraggable: slotStub('VibeDraggable', { isDragging: false }),
};
