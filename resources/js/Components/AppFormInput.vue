<script setup lang="ts">
defineOptions({ inheritAttrs: false });

const model = defineModel<string | number | null | undefined>();
</script>

<template>
    <VibeFormInput v-model="model" v-bind="$attrs" />
</template>
