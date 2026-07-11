<script setup lang="ts">
import { ref } from 'vue';
import WallComposer from './WallComposer.vue';
import WallPostCard, { type WallPostShape } from './WallPostCard.vue';
import { http } from '../../lib/http';

const props = defineProps<{
    posts: WallPostShape[];
    wallUserId?: number | null;
}>();

// Inertia refreshes `posts` on post/delete/react; `older` holds pages the
// user pulled in via Load more (append-only, keyed dedupe below).
const older = ref<WallPostShape[]>([]);
const hasMore = ref(props.posts.length >= 25);
const loading = ref(false);

async function loadMore(): Promise<void> {
    if (loading.value) return;
    loading.value = true;
    try {
        const last = older.value.length ? older.value[older.value.length - 1] : props.posts[props.posts.length - 1];
        const params = new URLSearchParams({ before: String(last.id) });
        if (props.wallUserId) params.set('wall_user_id', String(props.wallUserId));
        const data = await http.get(`/wall?${params}`);
        const known = new Set([...props.posts, ...older.value].map((p) => p.id));
        older.value.push(...(data?.posts ?? []).filter((p: WallPostShape) => !known.has(p.id)));
        hasMore.value = !!data?.hasMore;
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <section class="wall d-flex flex-column gap-2" aria-label="Wall">
        <h2 class="h6 text-muted text-uppercase fw-semibold mb-0">The Wall</h2>
        <WallComposer :wall-user-id="wallUserId" />
        <p v-if="!posts.length && !older.length" class="text-muted small mb-0" data-testid="wall-empty">
            Nothing here yet. Say something.
        </p>
        <WallPostCard v-for="post in posts" :key="post.id" :post="post" />
        <WallPostCard v-for="post in older" :key="post.id" :post="post" />
        <div v-if="hasMore" class="text-center">
            <VibeButton size="sm" variant="secondary" outline :disabled="loading" @click="loadMore">
                <VibeSpinner v-if="loading" size="sm" class="me-1" />Load more
            </VibeButton>
        </div>
    </section>
</template>
