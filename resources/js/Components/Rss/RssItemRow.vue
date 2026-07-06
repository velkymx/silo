<script setup lang="ts">
import { computed } from 'vue';
import { useRelativeTime } from '../../composables/useRelativeTime';

interface Item {
    id: number;
    feed_id: number;
    feed_title: string | null;
    feed_folder: string | null;
    title: string;
    excerpt: string | null;
    author: string | null;
    categories?: string[];
    image_url?: string | null;
    url: string;
    published_at: string | null;
    is_read: boolean;
    is_starred: boolean;
}

const props = defineProps<{ item: Item; selected: boolean }>();
const emit = defineEmits<{ click: []; 'toggle-star': [] }>();

const { short } = useRelativeTime();
const when = computed(() => short(props.item.published_at));

const cats = computed(() => (props.item.categories ?? []).filter((c) => c && c.trim() !== '').slice(0, 3));
</script>

<template>
    <div
        class="rss-row d-flex align-items-start gap-2 px-2 py-2 rounded"
        :class="{ 'rss-row--selected': selected, 'rss-row--unread': !item.is_read }"
        role="button"
        @click="emit('click')"
    >
        <div class="rss-row__dot mt-2 flex-shrink-0" :class="{ 'rss-row__dot--unread': !item.is_read }"></div>
        <img v-if="item.image_url" :src="item.image_url" alt="" class="rss-row__thumb flex-shrink-0" loading="lazy" @error="($event.target as HTMLImageElement).style.display = 'none'">
        <div class="flex-grow-1 min-w-0">
            <div class="d-flex align-items-center gap-2">
                <span class="fw-semibold text-truncate" :title="item.title">{{ item.title }}</span>
                <VibeIcon v-if="item.is_starred" icon="star-fill" class="text-warning flex-shrink-0" />
            </div>
            <div v-if="item.excerpt" class="small text-muted text-truncate" :title="item.excerpt">{{ item.excerpt }}</div>
            <div v-if="cats.length" class="d-flex align-items-center gap-1 mt-1 flex-wrap">
                <span v-for="c in cats" :key="c" class="badge text-bg-light border">{{ c }}</span>
            </div>
            <div class="small text-muted d-flex align-items-center gap-2 mt-1">
                <span class="text-truncate" :title="item.feed_title ?? ''">
                    <VibeIcon icon="rss-fill" class="me-1 text-warning" />{{ item.feed_title }}
                </span>
                <span v-if="item.author" class="text-truncate" :title="item.author">· {{ item.author }}</span>
                <span class="ms-auto flex-shrink-0">{{ when }}</span>
            </div>
        </div>
        <VibeButton
            variant="link"
            size="sm"
            class="p-1 flex-shrink-0"
            :title="item.is_starred ? 'Unstar' : 'Star'"
            :aria-label="item.is_starred ? 'Unstar' : 'Star'"
            @click.stop="emit('toggle-star')"
        >
            <VibeIcon :icon="item.is_starred ? 'star-fill' : 'star'" :class="item.is_starred ? 'text-warning' : 'text-muted'" />
        </VibeButton>
    </div>
</template>

<style scoped>
.rss-row {
    cursor: pointer;
    background: transparent;
    transition: background 0.1s;
}
.rss-row:hover {
    background: rgba(var(--bs-primary-rgb), 0.06);
}
.rss-row--selected {
    background: rgba(var(--bs-primary-rgb), 0.12);
}
.rss-row--unread .fw-semibold {
    color: var(--bs-body-color);
}
.rss-row__dot {
    width: 0.5rem;
    height: 0.5rem;
    border-radius: 50%;
    background: transparent;
    flex-shrink: 0;
}
.rss-row__dot--unread {
    background: var(--bs-primary);
}
.rss-row__thumb {
    width: 64px;
    height: 64px;
    object-fit: cover;
    border-radius: var(--radius-md, 0.5rem);
    background: var(--bs-tertiary-bg);
}
</style>

