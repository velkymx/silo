<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick, watch } from 'vue';
import { router, usePage } from '@inertiajs/vue3';
import { useColorMode } from '@velkymx/vibeui';
import { useCommandPalette } from '../composables/useCommandPalette';

const { state: palette, close: paletteClose } = useCommandPalette();
const { toggleColorMode } = useColorMode();
const page = usePage();

const q = ref('');
const cursor = ref(0);
const listEl = ref(null);
const inputEl = ref(null);

const allGroups = computed(() => {
    const folder = (page.props.currentFolder ?? null);
    const goTo = (path) => () => { close(); router.get(path); };

    const navigation = [
        { text: 'Go to Files', icon: 'folder', run: goTo('/') },
        { text: 'Go to Photos', icon: 'image', run: goTo('/photos') },
        { text: 'Go to Bookmarks', icon: 'bookmark', run: goTo('/bookmarks') },
        { text: 'Go to Notes', icon: 'journal-text', run: goTo('/notes') },
        { text: 'Go to Vault', icon: 'shield-lock', run: goTo('/vault') },
    ];

    const actions = [
        { text: 'New folder', icon: 'folder-plus', run: () => { close(); router.visit('/?new=folder'); } },
        { text: 'Empty trash', icon: 'trash', run: () => { close(); router.delete('/trash/empty', { preserveScroll: true }); } },
        { text: 'Switch theme', icon: 'circle-half', run: () => { toggleColorMode(); close(); } },
    ];

    if (folder && folder.id) {
        actions.push({
            text: 'New folder in this folder',
            icon: 'folder-plus',
            run: () => { close(); router.visit(`/?new=folder&parent=${folder.id}`); },
        });
    }

    return [
        { group: 'Navigation', entries: navigation },
        { group: 'Actions', entries: actions },
    ];
});

const filteredGroups = computed(() => {
    const term = q.value.trim().toLowerCase();
    const matches = (text) => !term || text.toLowerCase().includes(term);
    const groups = allGroups.value
        .map(g => ({ group: g.group, entries: g.entries.filter(e => matches(e.text)) }))
        .filter(g => g.entries.length);

    if (term) {
        groups.unshift({
            group: 'Search files',
            entries: [{
                text: `Search files for "${term}"`,
                icon: 'search',
                run: () => {
                    close();
                    const params = { search: term };
                    if (page.props.currentFolder) {
                        params.scope = 'folder';
                        params.folder = String(page.props.currentFolder.id);
                    }
                    router.get('/', params);
                },
            }],
        });
    }

    return groups;
});

const flatItems = computed(() =>
    filteredGroups.value.flatMap(g => g.entries)
);

watch(() => palette.open, async (open) => {
    if (open) {
        q.value = '';
        cursor.value = 0;
        await nextTick();
        const input = listEl.value?.querySelector('input');
        input?.focus();
    }
});

watch(filteredGroups, () => { cursor.value = 0; });

function close() {
    q.value = '';
    paletteClose();
}

function runItem(item) {
    item.run();
}

function onKey(e) {
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
            runItem(item);
        }
    } else if (e.key === 'Escape') {
        e.preventDefault();
        close();
    }
}

function scrollToCursor() {
    nextTick(() => {
        const el = listEl.value?.querySelector(`[data-cursor="${cursor.value}"]`);
        el?.scrollIntoView({ block: 'nearest' });
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
            <div class="px-3 pt-3">
                <VibeFormInput
                    v-model="q"
                    type="search"
                    placeholder="Type a command or search…"
                    :autocomplete="'off'"
                    ref="inputEl"
                />
            </div>
            <div class="command-list">
                <template v-for="group in filteredGroups" :key="group.group">
                    <div class="command-group-label text-uppercase text-muted small px-3 pt-2">
                        {{ group.group }}
                    </div>
                    <ul class="list-group list-group-flush">
                        <li
                            v-for="(entry, gi) in group.entries"
                            :key="group.group + '-' + gi"
                            class="list-group-item list-group-item-action d-flex align-items-center gap-2 command-item"
                            :class="{ active: flatItems.indexOf(entry) === cursor }"
                            :data-cursor="flatItems.indexOf(entry)"
                            role="button"
                            tabindex="0"
                            @click="runItem(entry)"
                            @mouseenter="cursor = flatItems.indexOf(entry)"
                            @keydown.enter.prevent="runItem(entry)"
                        >
                            <VibeIcon :icon="entry.icon" />
                            <span class="flex-grow-1">{{ entry.text }}</span>
                        </li>
                    </ul>
                </template>
                <div v-if="!flatItems.length" class="px-3 py-4 text-center text-muted">
                    No commands match.
                </div>
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
