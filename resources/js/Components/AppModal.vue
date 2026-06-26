<script setup lang="ts">
import { ref, watch, nextTick } from 'vue';

defineOptions({ inheritAttrs: false });

const open = defineModel<boolean>({ required: true });

const root = ref<HTMLElement | null>(null);

const FOCUSABLE = 'input:not([type="hidden"]):not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]):not(.btn-close)';

watch(open, async (v) => {
    if (!v) return;
    await nextTick();
    root.value?.querySelector<HTMLElement>(FOCUSABLE)?.focus();
});

function onKeydown(e: KeyboardEvent) {
    if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
        root.value?.querySelector<HTMLFormElement>('form')?.requestSubmit();
    }
}
</script>

<template>
    <div ref="root" @keydown="onKeydown">
        <VibeModal v-model="open" v-bind="$attrs">
            <slot />
            <template v-if="$slots.header" #header><slot name="header" /></template>
            <template v-if="$slots.footer" #footer><slot name="footer" /></template>
        </VibeModal>
    </div>
</template>
