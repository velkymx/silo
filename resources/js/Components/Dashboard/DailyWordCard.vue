<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

// One-line invitation, only while today's puzzle is unfinished. Silence once
// it's done — the home screen never celebrates, it only offers next actions.
defineProps<{ waiting: boolean }>();
</script>

<template>
    <Link
        v-if="waiting"
        href="/break/dwg"
        class="daily-word card text-decoration-none"
        data-testid="daily-word"
    >
        <div class="card-body py-2 d-flex align-items-center gap-2">
            <VibeIcon icon="type" class="text-primary flex-shrink-0" />
            <span class="fw-semibold">Daily Word is waiting for you</span>
            <span class="ms-auto flex-shrink-0 fw-semibold">Play <VibeIcon icon="arrow-right" /></span>
        </div>
    </Link>
</template>

<style scoped>
.daily-word {
    color: var(--bs-body-color);
}
.daily-word:hover {
    border-color: var(--bs-primary);
}
</style>
