<script setup lang="ts">
import { ref, computed, onMounted, onBeforeUnmount, nextTick, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useColorMode } from '@velkymx/vibeui';
import { useCommandPalette } from '../composables/useCommandPalette';

interface Entry {
    icon: string;
    text: string;
    sub?: string;
    run: () => void;
}

interface Section {
    label: string;
    entries: Entry[];
}

interface QuickResult {
    id: number;
    title: string;
    snippet: string | null;
    url: string;
    meta?: Record<string, unknown>;
}

const { state: palette, close: paletteClose } = useCommandPalette();
const { toggleColorMode } = useColorMode();
const page = usePage();

const q = ref('');
const cursor = ref(0);
const listEl = ref<HTMLElement | null>(null);

const results = ref<Record<string, QuickResult[]>>({});
const loading = ref(false);
const fetchError = ref(false);

// --- Scope: default to the surface the user is on, toggle to "All of Silo".
const GROUP_META: Record<string, { label: string; icon: string }> = {
    files: { label: 'Files', icon: 'folder' },
    notes: { label: 'Notes', icon: 'journal-text' },
    rss: { label: 'Articles', icon: 'rss' },
    bookmarks: { label: 'Bookmarks', icon: 'bookmark' },
    people: { label: 'People', icon: 'person-rolodex' },
};

function surfaceFor(url: string): string | null {
    const p = url.split('?')[0];
    if (p.startsWith('/rss')) return 'rss';
    if (p.startsWith('/notes')) return 'notes';
    if (p.startsWith('/bookmarks')) return 'bookmarks';
    if (p.startsWith('/directory') || p.startsWith('/users')) return 'people';
    if (['/', '/recent', '/starred', '/trash', '/shared'].includes(p)) return 'files';
    return null;
}

const areaScope = computed<string | null>(() => surfaceFor(page.url));
const areaLabel = computed<string>(() => (areaScope.value ? GROUP_META[areaScope.value].label : ''));
const scope = ref<string>('all');

function setScope(next: string): void {
    scope.value = next;
}

// --- Live fetch (debounced + abortable).
let controller: AbortController | null = null;
let debounceId: ReturnType<typeof setTimeout> | undefined;

function scheduleFetch(): void {
    clearTimeout(debounceId);
    if (!q.value.trim()) {
        controller?.abort();
        results.value = {};
        loading.value = false;
        fetchError.value = false;
        return;
    }
    debounceId = setTimeout(runFetch, 200);
}

async function runFetch(): Promise<void> {
    controller?.abort();
    controller = new AbortController();
    const signal = controller.signal;
    loading.value = true;
    fetchError.value = false;
    try {
        const url = `/search/quick?q=${encodeURIComponent(q.value.trim())}&scope=${scope.value}`;
        const res = await fetch(url, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            signal,
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
        if (signal.aborted) return;
        results.value = data.results ?? {};
    } catch (e) {
        if ((e as { name?: string }).name === 'AbortError') return;
        fetchError.value = true;
        results.value = {};
    } finally {
        if (!signal.aborted) loading.value = false;
    }
}

watch([q, scope], scheduleFetch);

// --- Commands (navigation + actions), filtered by the term.
const commandSections = computed<Section[]>(() => {
    const term = q.value.trim().toLowerCase();
    const matches = (text: string) => !term || text.toLowerCase().includes(term);
    const goTo = (path: string) => () => { close(); router.get(path); };

    const navigation: Entry[] = [
        { text: 'Go to Files', icon: 'folder', run: goTo('/') },
        { text: 'Go to Photos', icon: 'image', run: goTo('/photos') },
        { text: 'Go to Bookmarks', icon: 'bookmark', run: goTo('/bookmarks') },
        { text: 'Go to Notes', icon: 'journal-text', run: goTo('/notes') },
        { text: 'Go to Vault', icon: 'shield-lock', run: goTo('/vault') },
    ];

    const actions: Entry[] = [
        { text: 'New folder', icon: 'folder-plus', run: () => { close(); router.visit('/?new=folder'); } },
        { text: 'Switch theme', icon: 'circle-half', run: () => { toggleColorMode(); close(); } },
    ];

    return [
        { label: 'Navigation', entries: navigation.filter((e) => matches(e.text)) },
        { label: 'Actions', entries: actions.filter((e) => matches(e.text)) },
    ].filter((s) => s.entries.length);
});

// --- Result sections built from the live fetch.
const resultSections = computed<Section[]>(() => {
    const term = q.value.trim();
    if (!term) return [];

    const sections: Section[] = [];
    for (const key of ['files', 'notes', 'rss', 'bookmarks', 'people']) {
        const rows = results.value[key] ?? [];
        if (!rows.length) continue;
        sections.push({
            label: GROUP_META[key].label,
            entries: rows.map((r) => ({
                icon: GROUP_META[key].icon,
                text: r.title,
                sub: r.snippet ?? undefined,
                run: () => navigate(r.url),
            })),
        });
    }

    if (sections.length) {
        sections.push({
            label: '',
            entries: [{
                icon: 'search',
                text: `See all results for "${term}"`,
                run: () => { close(); router.get('/search', { q: term }); },
            }],
        });
    }

    return sections;
});

const sections = computed<Section[]>(() => [...resultSections.value, ...commandSections.value]);
const flatItems = computed<Entry[]>(() => sections.value.flatMap((s) => s.entries));

const showEmpty = computed<boolean>(() =>
    q.value.trim().length > 0 && !loading.value && !fetchError.value && !flatItems.value.length,
);

function navigate(url: string): void {
    close();
    if (/^https?:\/\//i.test(url)) {
        window.location.href = url;
    } else {
        router.get(url);
    }
}

watch(() => palette.open, async (open) => {
    if (open) {
        q.value = '';
        cursor.value = 0;
        results.value = {};
        scope.value = areaScope.value ?? 'all';
        await nextTick();
        listEl.value?.querySelector('input')?.focus();
    }
});

watch(sections, () => { cursor.value = 0; });

function close(): void {
    q.value = '';
    paletteClose();
}

function onKey(e: KeyboardEvent): void {
    if (!palette.open) return;
    const max = flatItems.value.length - 1;
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        cursor.value = Math.min(max, cursor.value + 1);
        scrollToCursor();
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        cursor.value = Math.max(0, cursor.value - 1);
        scrollToCursor();
    } else if (e.key === 'Enter') {
        const item = flatItems.value[cursor.value];
        if (item) {
            e.preventDefault();
            item.run();
        }
    } else if (e.key === 'Escape') {
        e.preventDefault();
        close();
    }
}

function scrollToCursor(): void {
    nextTick(() => {
        listEl.value?.querySelector(`[data-cursor="${cursor.value}"]`)?.scrollIntoView({ block: 'nearest' });
    });
}

onMounted(() => document.addEventListener('keydown', onKey));
onBeforeUnmount(() => document.removeEventListener('keydown', onKey));
</script>

<template>
    <VibeModal
        v-model="palette.open"
        title="Command Palette"
        size="lg"
        :hide-header="true"
        :hide-footer="true"
        :static-backdrop="false"
        teleport="body"
    >
        <div class="command-palette" ref="listEl">
            <div class="px-3 pt-3 d-flex align-items-center gap-2">
                <div class="btn-group btn-group-sm flex-shrink-0" role="group" aria-label="Search scope">
                    <button
                        v-if="areaScope"
                        type="button"
                        class="btn"
                        :class="scope === areaScope ? 'btn-primary' : 'btn-outline-secondary'"
                        data-scope="area"
                        @click="setScope(areaScope)"
                    >
                        {{ areaLabel }}
                    </button>
                    <button
                        type="button"
                        class="btn"
                        :class="scope === 'all' ? 'btn-primary' : 'btn-outline-secondary'"
                        data-scope="all"
                        @click="setScope('all')"
                    >
                        All of Silo
                    </button>
                </div>
                <VibeFormInput
                    v-model="q"
                    type="search"
                    class="flex-grow-1"
                    placeholder="Search or type a command…"
                    autocomplete="off"
                    no-wrapper
                />
            </div>
            <div class="command-list">
                <div v-if="loading" class="px-3 py-2 text-muted small d-flex align-items-center gap-2">
                    <VibeSpinner size="sm" /> Searching…
                </div>
                <div v-if="fetchError" class="px-3 py-2 text-danger small">Search failed, try again.</div>

                <template v-for="(section, si) in sections" :key="si + '-' + section.label">
                    <div v-if="section.label" class="command-group-label text-uppercase text-muted small px-3 pt-2">
                        {{ section.label }}
                    </div>
                    <ul class="list-group list-group-flush">
                        <li
                            v-for="entry in section.entries"
                            :key="flatItems.indexOf(entry)"
                            class="list-group-item list-group-item-action d-flex align-items-center gap-2 command-item"
                            :class="{ active: flatItems.indexOf(entry) === cursor }"
                            :data-cursor="flatItems.indexOf(entry)"
                            role="button"
                            tabindex="0"
                            @click="entry.run()"
                            @mouseenter="cursor = flatItems.indexOf(entry)"
                            @keydown.enter.prevent="entry.run()"
                        >
                            <VibeIcon :icon="entry.icon" />
                            <span class="flex-grow-1 min-w-0">
                                <span class="d-block text-truncate">{{ entry.text }}</span>
                                <span v-if="entry.sub" class="d-block small text-muted text-truncate">{{ entry.sub }}</span>
                            </span>
                        </li>
                    </ul>
                </template>

                <div v-if="showEmpty" class="px-3 py-4 text-center text-muted">No results.</div>
            </div>
        </div>
    </VibeModal>
</template>

<style scoped>
.command-palette {
    display: flex;
    flex-direction: column;
    max-height: 60vh;
}
.command-list {
    overflow-y: auto;
    flex-grow: 1;
}
.command-item {
    cursor: pointer;
}
.command-group-label {
    font-size: 0.7rem;
    letter-spacing: 0.05em;
}
</style>
