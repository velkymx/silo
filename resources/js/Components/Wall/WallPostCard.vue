<script setup lang="ts">
import { computed } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import UserAvatar from '../UserAvatar.vue';
import WallReactions, { type WallReaction } from './WallReactions.vue';
import { sanitizeHtml } from '../../lib/sanitize';
import { timeAgo } from '../../lib/relativeTime';
import { useConfirm } from '../../composables/useConfirm';

export interface WallPostShape {
    id: number;
    body: string;
    created_at: string;
    author: { id: number; name: string; avatar_url: string | null };
    can_delete: boolean;
    reactions: WallReaction[];
}

const props = defineProps<{ post: WallPostShape }>();

const { confirm } = useConfirm();

// Server sanitizes at store; sanitize again before innerHTML (house rule:
// nothing reaches innerHTML unsanitized).
const safeBody = computed(() => sanitizeHtml(props.post.body));

async function remove(): Promise<void> {
    if (!await confirm({ title: 'Delete post', message: 'Remove this post from the wall?', confirmLabel: 'Delete', variant: 'danger' })) return;
    router.delete(`/wall/${props.post.id}`, { preserveScroll: true });
}
</script>

<template>
    <div class="wall-post card">
        <div class="card-body py-2 px-3">
            <div class="d-flex align-items-center gap-2 mb-1">
                <UserAvatar :user="post.author" :size="32" />
                <Link :href="`/directory/${post.author.id}`" class="fw-semibold text-decoration-none text-body">
                    {{ post.author.name }}
                </Link>
                <span class="small text-muted">{{ timeAgo(post.created_at) }}</span>
                <button
                    v-if="post.can_delete"
                    type="button"
                    class="btn btn-sm btn-link text-danger ms-auto p-0"
                    aria-label="Delete post"
                    data-testid="wall-delete"
                    @click="remove"
                >
                    <VibeIcon icon="trash" />
                </button>
            </div>
            <!-- eslint-disable-next-line vue/no-v-html -- sanitized server-side AND through sanitizeHtml above -->
            <div class="wall-post__body mb-2" v-html="safeBody" />
            <WallReactions :post-id="post.id" :reactions="post.reactions" />
        </div>
    </div>
</template>

<style scoped>
.wall-post__body :deep(p:last-child) {
    margin-bottom: 0;
}
.wall-post__body :deep(img) {
    max-width: 100%;
}
</style>
