<script setup lang="ts">
// VibeUI 1.1 ships aria-describedby wiring and role="alert" on errors natively.
// This shim delegates to VibeFormGroup; delete when all consumers use <VibeFormGroup> directly.
defineOptions({ inheritAttrs: false });
</script>

<template>
    <VibeFormGroup v-bind="$attrs">
        <slot />
    </VibeFormGroup>
</template>
