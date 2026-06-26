<script setup lang="ts">
defineOptions({ inheritAttrs: false });
</script>

<template>
    <VibeButton v-bind="$attrs"><slot /></VibeButton>
</template>
