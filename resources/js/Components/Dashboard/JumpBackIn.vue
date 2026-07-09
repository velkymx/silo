<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

export interface JumpBackInItem {
    id: number;
    title: string;
    type: 'file' | 'note';
    url: string;
    editedAt: string;
}

const props = defineProps<{ item: JumpBackInItem | null }>();

// Relative "edited N ago" phrasing. Kept local: the home screen is the only
// surface that needs it, and it avoids pulling in a date library for one line.
const editedAgo = computed<string>(() => {
    if (!props.item) return '';
    const then = new Date(props.item.editedAt).getTime();
    const secs = Math.max(0, Math.round((Date.now() - then) / 1000));
    if (secs < 60) return 'just now';
    const mins = Math.round(secs / 60);
    if (mins < 60) return `${mins} min ago`;
    const hours = Math.round(mins / 60);
    if (hours < 24) return `${hours} hr ago`;
    const days = Math.round(hours / 24);
    return days === 1 ? 'yesterday' : `${days} days ago`;
});

const icon = computed<string>(() => (props.item?.type === 'note' ? 'journal-text' : 'file-earmark'));
</script>

<template>
    <Link
        v-if="item"
        :href="item.url"
        class="jump-back-in d-flex align-items-center gap-3 p-3 rounded-3 text-decoration-none"
        data-testid="jump-back-in"
    >
        <span class="jump-back-in__icon d-flex align-items-center justify-content-center rounded-3 flex-shrink-0">
            <VibeIcon :icon="icon" class="fs-4" />
        </span>
        <span class="flex-grow-1 min-w-0">
            <span class="d-block small text-uppercase fw-semibold jump-back-in__eyebrow">Jump back in</span>
            <span class="d-block fw-semibold text-truncate jump-back-in__title">{{ item.title }}</span>
            <span class="d-block small jump-back-in__meta">Edited {{ editedAgo }}</span>
        </span>
        <VibeIcon icon="arrow-right" class="fs-5 flex-shrink-0 jump-back-in__chevron" />
    </Link>
</template>

<style scoped>
.jump-back-in {
    background: var(--bs-primary);
    color: #fff;
    transition: filter 0.15s ease;
}
.jump-back-in:hover {
    filter: brightness(1.06);
    color: #fff;
}
.jump-back-in__icon {
    width: 3rem;
    height: 3rem;
    background: rgba(255, 255, 255, 0.18);
}
.jump-back-in__eyebrow {
    letter-spacing: 0.06em;
    opacity: 0.85;
}
.jump-back-in__meta {
    opacity: 0.85;
}
.jump-back-in__chevron {
    opacity: 0.9;
}
</style>
