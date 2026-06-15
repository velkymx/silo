<script setup lang="ts">
withDefaults(defineProps<{ icon?: string; title: string; hint?: string }>(), { icon: 'inbox', hint: '' });
</script>

<template>
    <div class="text-center text-muted py-5">
        <VibeIcon :icon="icon" class="display-5 d-block mb-2 opacity-50" />
        <div class="fw-medium">{{ title }}</div>
        <div v-if="hint" class="small">{{ hint }}</div>
    </div>
</template>
