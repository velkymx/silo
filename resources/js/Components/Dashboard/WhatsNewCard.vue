<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

export interface WhatsNewArticle {
    id: number;
    title: string;
    feed: string | null;
    url: string;
}

export interface WhatsNew {
    unreadCount: number;
    articles: WhatsNewArticle[];
    inboxUrl: string;
}

// Deliberately compact: the home screen is about the user's work, not a feed.
// One line: unread count, the newest headline for scent, and the inbox link.
defineProps<{ whatsNew: WhatsNew | null }>();
</script>

<template>
    <section v-if="whatsNew" class="whats-new" aria-label="What's new">
        <h2 class="h6 text-muted text-uppercase fw-semibold mb-2 whats-new__heading">What's New</h2>
        <Link
            :href="whatsNew.inboxUrl"
            class="card text-decoration-none whats-new__strip"
            data-testid="whats-new-strip"
        >
            <div class="card-body py-2 d-flex align-items-center gap-2">
                <VibeIcon icon="rss" class="text-secondary flex-shrink-0" />
                <span class="fw-semibold flex-shrink-0">
                    {{ whatsNew.unreadCount }} unread {{ whatsNew.unreadCount === 1 ? 'article' : 'articles' }}
                </span>
                <span v-if="whatsNew.articles.length" class="text-muted text-truncate min-w-0 d-none d-sm-inline">
                    &middot; {{ whatsNew.articles[0].title }}
                </span>
                <span class="ms-auto flex-shrink-0 fw-semibold">View Inbox <VibeIcon icon="arrow-right" /></span>
            </div>
        </Link>
    </section>
</template>

<style scoped>
.whats-new__strip {
    color: var(--bs-body-color);
}
.whats-new__strip:hover {
    border-color: var(--bs-primary);
}
</style>
