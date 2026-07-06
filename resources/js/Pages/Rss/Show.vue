<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import ShellLayout from '../../Layouts/ShellLayout.vue';
import { sanitizeHtml } from '../../lib/sanitize';

interface Item {
    id: number;
    feed_id: number;
    feed_title: string | null;
    feed_site_url: string | null;
    title: string;
    content: string | null;
    excerpt: string | null;
    author: string | null;
    url: string;
    published_at: string | null;
    is_read: boolean;
    is_starred: boolean;
}

interface Related {
    id: number;
    title: string;
    published_at: string | null;
    is_read: boolean;
}

const props = defineProps<{ item: Item; related: Related[] }>();

// Content is sanitized server-side on ingest; this is defense-in-depth so a
// raw body can never reach the DOM even if an unsanitized row slips through.
const safeContent = computed(() => (props.item.content ? sanitizeHtml(props.item.content) : ''));

const activePane = ref('contents');
function toggleStar(): void {
    router.post(`/rss/items/${props.item.id}/star`, {}, { preserveScroll: true, preserveState: true });
}
function markRead(): void {
    if (props.item.is_read) return;
    router.post(`/rss/items/${props.item.id}/read`, {}, { preserveScroll: true, preserveState: true });
}
function markUnread(): void {
    if (!props.item.is_read) return;
    router.post(`/rss/items/${props.item.id}/unread`, {}, { preserveScroll: true, preserveState: true });
}
</script>

<template>
    <ShellLayout v-model:active-pane="activePane" :detail-visible="false" :folders-visible="false">
        <template #topBar>
            <div class="d-flex align-items-center gap-2 p-2">
                <Link href="/rss" class="btn btn-secondary btn-sm">
                    <VibeIcon icon="chevron-left" class="me-1" />Back to inbox
                </Link>
                <div class="ms-auto d-flex align-items-center gap-2">
                    <VibeButton size="sm" variant="secondary" @click="markRead" :disabled="item.is_read">
                        <VibeIcon icon="check2" class="me-1" />Mark read
                    </VibeButton>
                    <VibeButton size="sm" variant="secondary" @click="markUnread" :disabled="!item.is_read">
                        <VibeIcon icon="circle" class="me-1" />Mark unread
                    </VibeButton>
                    <VibeButton size="sm" variant="secondary" @click="toggleStar">
                        <VibeIcon :icon="item.is_starred ? 'star-fill' : 'star'" :class="item.is_starred ? 'text-warning' : ''" class="me-1" />
                        {{ item.is_starred ? 'Starred' : 'Star' }}
                    </VibeButton>
                    <VibeButton size="sm" variant="primary" :href="item.url" target="_blank" rel="noopener">
                        <VibeIcon icon="box-arrow-up-right" class="me-1" />Open original
                    </VibeButton>
                </div>
            </div>
        </template>

        <template #contents>
            <article class="rss-article mx-auto p-4" style="max-width: 760px">
                <header class="mb-3">
                    <a :href="item.feed_site_url ?? '#'" target="_blank" rel="noopener" class="small text-muted text-decoration-none d-inline-flex align-items-center gap-1 mb-2">
                        <VibeIcon icon="rss-fill" class="text-warning" />{{ item.feed_title }}
                    </a>
                    <h1 class="display-6 mb-2">{{ item.title }}</h1>
                    <div class="small text-muted">
                        <span v-if="item.author">{{ item.author }}</span>
                        <template v-if="item.author && item.published_at"> · </template>
                        <span v-if="item.published_at">{{ new Date(item.published_at).toLocaleString() }}</span>
                    </div>
                </header>

                <div v-if="item.excerpt" class="lead text-muted mb-3">{{ item.excerpt }}</div>

                <div v-if="safeContent" class="rss-content" v-html="safeContent"></div>
                <p v-else class="text-muted">No content provided by the feed.</p>

                <hr class="my-4">
                <h2 class="h6 text-uppercase text-muted">More from this feed</h2>
                <ul class="list-unstyled m-0">
                    <li v-for="r in related" :key="r.id" class="py-1">
                        <Link :href="`/rss/items/${r.id}`" class="text-decoration-none d-flex align-items-center gap-2">
                            <VibeIcon :icon="r.is_read ? 'circle' : 'circle-fill'" :class="r.is_read ? 'text-muted' : 'text-primary'" class="small" />
                            <span class="text-truncate">{{ r.title }}</span>
                            <span v-if="r.published_at" class="small text-muted ms-auto">{{ new Date(r.published_at).toLocaleDateString() }}</span>
                        </Link>
                    </li>
                </ul>
            </article>
        </template>
    </ShellLayout>
</template>

<style scoped>
.rss-content :deep(a) {
    color: var(--bs-primary);
    text-decoration: underline;
}
.rss-content :deep(img) {
    max-width: 100%;
    height: auto;
    border-radius: 0.25rem;
    margin: 0.5rem 0;
}
.rss-content :deep(pre),
.rss-content :deep(code) {
    background: var(--bs-tertiary-bg);
    border-radius: 0.25rem;
    padding: 0.1em 0.35em;
}
</style>
