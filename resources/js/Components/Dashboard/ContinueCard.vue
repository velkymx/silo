<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { timeAgo } from '../../lib/relativeTime';

export interface ContinueItem {
    type: 'file' | 'note' | 'bookmark' | 'article';
    title: string;
    url: string;
    at: string;
    reason: 'edited' | 'added' | 'read';
}

defineProps<{ items: ContinueItem[] }>();

const icons: Record<ContinueItem['type'], string> = {
    file: 'file-earmark',
    note: 'journal-text',
    bookmark: 'bookmark',
    article: 'rss',
};

// Second line: object-first means the type/verb is secondary context.
function subtitle(item: ContinueItem): string {
    const ago = timeAgo(item.at);
    if (item.reason === 'added') return `Bookmark added ${ago}`;
    if (item.reason === 'read') return `Read ${ago}`;
    return `Edited ${ago}`;
}
</script>

<template>
    <section v-if="items.length" class="continue-card" aria-label="Continue working">
        <h2 class="h6 text-muted text-uppercase fw-semibold mb-2 continue-card__heading">Continue Working</h2>
        <div class="list-group">
            <Link
                v-for="item in items"
                :key="`${item.type}-${item.url}-${item.at}`"
                :href="item.url"
                class="list-group-item list-group-item-action d-flex align-items-start gap-3"
                :data-type="item.type"
            >
                <VibeIcon :icon="icons[item.type]" class="fs-5 text-secondary mt-1 flex-shrink-0" />
                <span class="min-w-0">
                    <span class="d-block fw-semibold text-truncate">{{ item.title }}</span>
                    <span class="d-block small text-muted">{{ subtitle(item) }}</span>
                </span>
            </Link>
        </div>
    </section>
</template>
