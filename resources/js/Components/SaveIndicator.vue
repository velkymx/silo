<script setup lang="ts">
defineProps<{
    state: 'idle' | 'saving' | 'saved' | 'error';
}>();
</script>

<template>
    <small class="text-muted" aria-live="polite" aria-atomic="true">
        <span v-if="state === 'saving'">Saving…</span>
        <span v-else-if="state === 'saved'">Saved</span>
        <span v-else-if="state === 'error'" class="text-danger">Save failed — check connection</span>
    </small>
</template>
