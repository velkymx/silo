<script setup lang="ts">
defineProps<{ count: number }>();
const emit = defineEmits<{ clear: [] }>();
</script>

<template>
    <div v-if="count" class="batch-bar d-flex flex-wrap align-items-center gap-2 border rounded bg-body p-2 mb-3 shadow-sm">
        <strong>{{ count }} selected</strong>
        <div class="ms-auto d-flex flex-wrap gap-2">
            <slot />
            <AppButton variant="secondary" size="sm" outline @click="emit('clear')">Clear</AppButton>
        </div>
    </div>
</template>
