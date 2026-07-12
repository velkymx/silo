<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import ShellLayout from '../../Layouts/ShellLayout.vue';
import EmptyState from '../../Components/EmptyState.vue';
import { useConfirm } from '../../composables/useConfirm';
import { useToast } from '../../composables/useToast';

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

interface SavedSearch {
    id: number;
    name: string;
    params: { q?: string; search?: string; [k: string]: unknown };
    is_favorite: boolean;
}

const props = defineProps<{
    q: string;
    results: Results;
    total: number;
    savedSearches?: SavedSearch[];
}>();

const { confirm } = useConfirm();
const toast = useToast();

const query = ref(props.q);
watch(() => props.q, (next) => { query.value = next; });

function go(): void {
    const v = query.value.trim();
    router.get('/search', v ? { q: v } : {});
}

const hasResults = computed(() => props.total > 0);

const savedGlobalSearches = computed(() => (props.savedSearches ?? [])
    .filter((s) => !!s.params.q)
    .sort((a, b) => {
        if (a.is_favorite !== b.is_favorite) return a.is_favorite ? -1 : 1;
        return a.name.localeCompare(b.name);
    })
);

async function saveCurrentSearch(): Promise<void> {
    const q = query.value.trim();
    if (!q) return;
    const name = window.prompt('Name this search:', q);
    if (!name?.trim()) return;
    await router.post('/saved-searches', { name: name.trim(), params: { q } });
    toast.push('Search saved', { variant: 'success' });
}

async function deleteSavedSearch(s: SavedSearch): Promise<void> {
    if (!await confirm({ title: 'Remove saved search', message: `Remove “${s.name}”?`, confirmLabel: 'Remove', variant: 'danger' })) return;
    await router.delete(`/saved-searches/${s.id}`);
}

async function togglePin(s: SavedSearch): Promise<void> {
    await router.post(`/saved-searches/${s.id}/favorite`);
}

function runSavedSearch(s: SavedSearch): void {
    router.get('/search', { q: s.params.q });
}

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
                <VibeButton
                    v-if="query.trim()"
                    size="sm"
                    variant="light"
                    title="Save this search"
                    aria-label="Save search"
                    @click="saveCurrentSearch"
                >
                    <VibeIcon icon="bookmark-plus" />
                </VibeButton>
            </div>
        </template>

        <template #contents>
            <div class="overflow-auto flex-grow-1">
            <div class="container py-3" style="max-width: 920px">
                <div v-if="!q" class="text-center text-muted py-5">
                    <VibeIcon icon="search" class="display-6 mb-2 d-block" />
                    <p class="mb-0">Type a query to search across files, articles, and bookmarks.</p>
                </div>
                <template v-else>
                    <div v-if="!hasResults" class="text-center text-muted py-5">
                        <VibeIcon icon="search" class="display-6 mb-2 d-block" />
                        <p class="mb-0">No results for “{{ q }}”.</p>
                    </div>
                    <template v-if="savedGlobalSearches.length">
                        <div class="d-flex align-items-center gap-2 flex-wrap mb-3 small">
                            <VibeIcon icon="bookmark-star-fill" class="text-primary" />
                            <span class="text-muted">Saved searches:</span>
                            <button
                                v-for="s in savedGlobalSearches"
                                :key="s.id"
                                type="button"
                                class="btn btn-link btn-sm p-0 d-inline-flex align-items-center text-decoration-none me-2"
                                @click="runSavedSearch(s)"
                            >
                                <VibeIcon v-if="s.is_favorite" icon="pin-fill" class="me-1 text-warning" />
                                <span>{{ s.name }}</span>
                                <button
                                    type="button"
                                    class="btn btn-sm p-0 ms-1 text-muted border-0 bg-transparent"
                                    :aria-label="`Toggle pin for ${s.name}`"
                                    :title="s.is_favorite ? 'Unpin' : 'Pin to top'"
                                    @click.stop="togglePin(s)"
                                ><VibeIcon :icon="s.is_favorite ? 'pin-angle-fill' : 'pin-angle'" /></button>
                                <button
                                    type="button"
                                    class="btn btn-sm p-0 ms-1 text-muted border-0 bg-transparent"
                                    :aria-label="`Remove saved search ${s.name}`"
                                    title="Remove"
                                    @click.stop="deleteSavedSearch(s)"
                                ><VibeIcon icon="x" /></button>
                            </button>
                        </div>
                    </template>

                    <section v-for="key in (['rss', 'files', 'bookmarks'] as Array<keyof Results>)" :key="key" class="mb-4">
                        <template v-if="results[key].length">
                            <h2 class="h6 text-uppercase text-muted d-flex align-items-center gap-2 mb-2">
                                <VibeIcon :icon="sectionMeta(key).icon" /> {{ sectionMeta(key).title }} ({{ sectionMeta(key).count }})
                            </h2>
                            <div class="list-group">
                                <a
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
                                </a>
                            </div>
                        </template>
                    </section>
                </template>
            </div>
            </div>
        </template>
    </ShellLayout>
</template>

