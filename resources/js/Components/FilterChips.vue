<script setup>
defineProps({
    chips: { type: Array, default: () => [] },
});
const emit = defineEmits(['clear-all']);
</script>

<template>
    <VibeAlert v-if="chips.length" variant="light" class="border d-flex flex-wrap align-items-center gap-2 py-2">
        <VibeIcon icon="funnel-fill" class="text-muted" />
        <VibeBadge
            v-for="chip in chips"
            :key="chip.key"
            variant="secondary"
            class="d-flex align-items-center gap-1"
        >
            <VibeIcon :icon="chip.icon" />{{ chip.label }}
            <VibeIcon icon="x" style="cursor: pointer" :aria-label="`Clear ${chip.label}`" @click="chip.clear()" />
        </VibeBadge>
        <VibeButton variant="link" size="sm" class="ms-auto p-0 text-decoration-none" @click="emit('clear-all')">Clear all</VibeButton>
    </VibeAlert>
</template>
