<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

export interface AttentionItem {
    tier: 'red' | 'yellow' | 'blue';
    title: string;
    url: string;
}

defineProps<{ items: AttentionItem[] }>();

// Subtle, never loud: a muted dot per tier rather than a full alert banner.
const dotClass: Record<AttentionItem['tier'], string> = {
    red: 'attention__dot--red',
    yellow: 'attention__dot--yellow',
    blue: 'attention__dot--blue',
};
</script>

<template>
    <section class="attention-card" aria-label="Needs attention">
        <h2 class="h6 text-muted text-uppercase fw-semibold mb-2 attention-card__heading">Needs Attention</h2>
        <p v-if="!items.length" class="text-muted small mb-0" data-testid="all-clear">All clear.</p>
        <div v-else class="list-group">
            <Link
                v-for="(item, i) in items"
                :key="i"
                :href="item.url"
                class="list-group-item list-group-item-action d-flex align-items-center gap-2"
                :data-tier="item.tier"
            >
                <span class="attention__dot flex-shrink-0" :class="dotClass[item.tier]" />
                <span class="min-w-0 text-truncate">{{ item.title }}</span>
            </Link>
        </div>
    </section>
</template>

<style scoped>
.attention__dot {
    width: 0.6rem;
    height: 0.6rem;
    border-radius: 50%;
    display: inline-block;
}
.attention__dot--red {
    background: var(--bs-danger);
}
.attention__dot--yellow {
    background: var(--bs-warning);
}
.attention__dot--blue {
    background: var(--bs-info);
}
</style>
