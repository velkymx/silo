<script setup lang="ts">
import type { Toast } from '../composables/useToast';

defineProps<{ items: Toast[] }>();
const emit = defineEmits<{
    dismiss: [id: number];
    undo: [toast: Toast];
}>();
</script>

<template>
    <div class="toast-host" aria-live="polite" aria-atomic="false">
        <div
            v-for="t in items"
            :key="t.id"
            class="toast-item d-flex align-items-center gap-2 px-3 py-2 rounded shadow-sm"
            :class="`bg-${t.variant === 'success' ? 'success' : t.variant === 'danger' ? 'danger' : 'info'}-subtle border border-${t.variant === 'success' ? 'success' : t.variant === 'danger' ? 'danger' : 'info'}-subtle text-body`"
            role="status"
        >
            <span class="flex-grow-1 small">{{ t.text }}</span>
            <button
                v-if="t.undo"
                type="button"
                class="btn btn-sm btn-outline-secondary py-0"
                @click="emit('undo', t)"
            >Undo</button>
            <button
                type="button"
                class="btn-close btn-close-sm"
                aria-label="Dismiss"
                @click="emit('dismiss', t.id)"
            />
        </div>
    </div>
</template>

<style scoped>
.toast-host {
    position: fixed;
    bottom: 1.25rem;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    z-index: 1090;
    min-width: 320px;
    max-width: 560px;
    pointer-events: none;
}
.toast-item {
    pointer-events: auto;
    animation: toast-in 0.2s ease;
}
@keyframes toast-in {
    from { opacity: 0; transform: translateY(8px); }
    to   { opacity: 1; transform: translateY(0); }
}
@media (prefers-reduced-motion: reduce) {
    .toast-item { animation: none; }
}
</style>
