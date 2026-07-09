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

defineProps<{ whatsNew: WhatsNew | null }>();
</script>

<template>
    <section v-if="whatsNew" class="whats-new" aria-label="What's new">
        <h2 class="h6 text-muted text-uppercase fw-semibold mb-2 whats-new__heading">What's New</h2>
        <div class="card">
            <div class="card-body">
                <p class="fw-semibold mb-2">
                    {{ whatsNew.unreadCount }} unread {{ whatsNew.unreadCount === 1 ? 'article' : 'articles' }}
                </p>
                <ul class="list-unstyled mb-3">
                    <li v-for="article in whatsNew.articles" :key="article.id" class="mb-2">
                        <Link :href="article.url" class="text-decoration-none d-block" :data-article="article.id">
                            <span class="d-block text-truncate">{{ article.title }}</span>
                            <span v-if="article.feed" class="d-block small text-muted">{{ article.feed }}</span>
                        </Link>
                    </li>
                </ul>
                <Link :href="whatsNew.inboxUrl" class="text-decoration-none fw-semibold">
                    View Inbox <VibeIcon icon="arrow-right" />
                </Link>
            </div>
        </div>
    </section>
</template>
