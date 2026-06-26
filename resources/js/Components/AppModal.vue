<script setup lang="ts">
// VibeUI 1.1 ships auto-focus, Cmd+Enter submit, and focus-trap natively.
// This shim delegates to VibeModal; delete when all consumers use <VibeModal> directly.
defineOptions({ inheritAttrs: false });
defineModel<boolean>({ required: true });
</script>

<template>
    <VibeModal v-bind="$attrs">
        <slot />
        <template v-if="$slots.header" #header><slot name="header" /></template>
        <template v-if="$slots.footer" #footer><slot name="footer" /></template>
    </VibeModal>
</template>
