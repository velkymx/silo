<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import ShellLayout from '../../Layouts/ShellLayout.vue';
import EmptyState from '../../Components/EmptyState.vue';

interface ResultItem {
    id: number;
    title: string;
    snippet: string | null;
    url: string;
    meta: Record<string, unknown>;
}

interface Results {
    files: ResultItem[];
    rss: ResultItem[];
    bookmarks: ResultItem[];
}

const props = defineProps<{
    q: string;
    results: Results;
    total: number;
}>();

const query = ref(props.q);
watch(() => props.q, (next) => { query.value = next; });

function go(): void {
    const v = query.value.trim();
    router.get('/search', v ? { q: v } : {});
}

const hasResults = computed(() => props.total > 0);

function sectionMeta(key: keyof Results): { title: string; icon: string; count: number } {
    const map: Record<keyof Results, { title: string; icon: string }> = {
        files: { title: 'Files', icon: 'file-earmark-text' },
        rss: { title: 'Articles', icon: 'rss' },
        bookmarks: { title: 'Bookmarks', icon: 'bookmark' },
    };
    return { ...map[key], count: props.results[key].length };
}
</script>

<template>
    <ShellLayout>
        <template #topBar>
            <div class="d-flex align-items-center gap-2 p-2 flex-grow-1">
                <VibeIcon icon="search" class="text-muted" />
                <input
                    v-model="query"
                    type="search"
                    class="form-control form-control-sm"
                    placeholder="Search files, articles, bookmarks…"
                    aria-label="Search"
                    @keyup.enter="go"
                >
                <VibeButton size="sm" variant="primary" @click="go">Search</VibeButton>
            </div>
        </template>

        <template #contents>
            <div class="container py-3" style="max-width: 920px">
                <div v-if="!q" class="text-center text-muted py-5">
                    <VibeIcon icon="search" class="display-6 mb-2 d-block" />
                    <p class="mb-0">Type a query to search across files, articles, and bookmarks.</p>
                </div>
                <div v-else-if="!hasResults" class="text-center text-muted py-5">
                    <VibeIcon icon="search" class="display-6 mb-2 d-block" />
                    <p class="mb-0">No results for “{{ q }}”.</p>
                </div>
                <template v-else>
                    <div class="d-flex align-items-center gap-2 mb-3 small text-muted">
                        <VibeIcon icon="search" />
                        <span>{{ total }} result{{ total === 1 ? '' : 's' }} for “<strong class="text-body">{{ q }}</strong>”</span>
                    </div>

                    <section v-for="key in (['rss', 'files', 'bookmarks'] as Array<keyof Results>)" :key="key" class="mb-4">
                        <template v-if="results[key].length">
                            <h2 class="h6 text-uppercase text-muted d-flex align-items-center gap-2 mb-2">
                                <VibeIcon :icon="sectionMeta(key).icon" /> {{ sectionMeta(key).title }} ({{ sectionMeta(key).count }})
                            </h2>
                            <div class="list-group">
                                <component
                                    :is="key === 'rss' ? 'a' : key === 'bookmarks' ? 'a' : 'a'"
                                    v-for="r in results[key]"
                                    :key="r.id"
                                    :href="r.url"
                                    class="list-group-item list-group-item-action"
                                    :target="key === 'bookmarks' ? '_blank' : undefined"
                                    :rel="key === 'bookmarks' ? 'noopener' : undefined"
                                >
                                    <div class="d-flex align-items-center gap-2">
                                        <span class="fw-semibold text-truncate">{{ r.title }}</span>
                                    </div>
                                    <div v-if="r.snippet" class="small text-muted text-truncate mt-1">{{ r.snippet }}</div>
                                    <div class="small text-muted mt-1 d-flex align-items-center gap-2 flex-wrap">
                                        <template v-if="key === 'rss'">
                                            <VibeIcon icon="rss-fill" class="text-warning" />
                                            <span v-if="(r.meta as { feed_title?: string }).feed_title">{{ (r.meta as { feed_title?: string }).feed_title }}</span>
                                            <template v-if="(r.meta as { author?: string }).author"> · {{ (r.meta as { author?: string }).author }}</template>
                                            <template v-if="(r.meta as { published_at?: string }).published_at"> · {{ new Date((r.meta as { published_at: string }).published_at).toLocaleDateString() }}</template>
                                        </template>
                                        <template v-else-if="key === 'bookmarks'">
                                            <VibeIcon icon="bookmark-fill" class="text-primary" />
                                            <span v-if="(r.meta as { site?: string }).site">{{ (r.meta as { site?: string }).site }}</span>
                                        </template>
                                        <template v-else>
                                            <VibeIcon icon="file-earmark" />
                                            <span v-if="(r.meta as { size?: number }).size">{{ Math.ceil(((r.meta as { size: number }).size || 0) / 1024) }} KB</span>
                                        </template>
                                    </div>
                                </component>
                            </div>
                        </template>
                    </section>
                </template>
            </div>
        </template>
    </ShellLayout>
</template>
