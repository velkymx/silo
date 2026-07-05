<script setup lang="ts">
import { computed } from 'vue';

interface Folder {
    id: number;
    name: string;
    parent_id: number | null;
    /** Optional icon override (section roots use their own glyph). */
    icon?: string;
}

const props = withDefaults(defineProps<{
    folders: Folder[];
    selectedId: number | null;
    openIds: Set<number>;
    parentId?: number | null;
    showHeader?: boolean;
    /** Optional per-folder item counts, shown as a badge on each row. */
    counts?: Record<number, number> | null;
}>(), {
    parentId: null,
    showHeader: true,
    counts: null,
});

const emit = defineEmits<{
    (e: 'select-folder', id: number): void;
    (e: 'new-folder'): void;
}>();

// Direct children of this level, alphabetised.
const children = computed(() =>
    props.folders
        .filter((f) => f.parent_id === props.parentId)
        .sort((a, b) => a.name.localeCompare(b.name)),
);

function hasChildren(id: number): boolean {
    return props.folders.some((f) => f.parent_id === id);
}

// Only folders WITH children become collapsible accordion items; leaves are
// plain rows. Toggling a collapse on an item with no body is what crashed
// Bootstrap's Collapse (null classList) — and leaves don't need chevrons.
function itemFor(f: Folder) {
    return [{
        id: String(f.id),
        title: f.name,
        content: '',
        show: props.openIds.has(f.id),
        icon: f.icon ?? null,
    }];
}
</script>

<template>
    <div class="folder-accordion w-100" :class="{ 'd-flex flex-column h-100 p-1': showHeader }">
        <div v-if="showHeader" class="d-flex align-items-center justify-content-between px-1 mb-1">
            <span class="fw-semibold small text-uppercase text-muted">Folders</span>
            <VibeButton size="sm" variant="light" title="New folder" aria-label="New folder" data-testid="fa-new" @click="emit('new-folder')">
                <VibeIcon icon="folder-plus" />
            </VibeButton>
        </div>

        <template v-for="f in children" :key="f.id">
            <VibeAccordion
                v-if="hasChildren(f.id)"
                flush
                always-open
                :items="itemFor(f)"
                @item-click="emit('select-folder', f.id)"
            >
                <template #title="{ item }">
                    <span :data-folder="item.id" class="d-flex align-items-center flex-grow-1 min-w-0" :class="{ 'fw-semibold': selectedId === f.id }">
                        <VibeIcon :icon="item.icon ?? (openIds.has(f.id) ? 'folder2-open' : 'folder2')" class="me-2" />
                        <span class="text-truncate flex-grow-1">{{ item.title }}</span>
                        <span v-if="counts && counts[f.id] != null" class="badge text-bg-light ms-2 flex-shrink-0">{{ counts[f.id] }}</span>
                    </span>
                </template>
                <template #content>
                    <FolderAccordion
                        :folders="folders"
                        :selected-id="selectedId"
                        :open-ids="openIds"
                        :parent-id="f.id"
                        :show-header="false"
                        :counts="counts"
                        @select-folder="emit('select-folder', $event)"
                        @new-folder="emit('new-folder')"
                    />
                </template>
            </VibeAccordion>
            <button
                v-else
                type="button"
                class="fa-leaf d-flex align-items-center w-100 text-start border-0 bg-transparent"
                :data-folder="f.id"
                :class="{ 'fw-semibold': selectedId === f.id }"
                @click="emit('select-folder', f.id)"
            >
                <VibeIcon :icon="f.icon ?? 'folder2'" class="me-2" />
                <span class="text-truncate flex-grow-1">{{ f.name }}</span>
                <span v-if="counts && counts[f.id] != null" class="badge text-bg-light ms-2 flex-shrink-0">{{ counts[f.id] }}</span>
            </button>
        </template>
    </div>
</template>

<style scoped>
.min-w-0 {
    min-width: 0;
}
/* Leaf rows line up with the flush accordion buttons around them. */
.fa-leaf {
    padding: 0.5rem 1.25rem;
    color: var(--bs-body-color);
    border-bottom: var(--bs-border-width) solid var(--bs-border-color);
    cursor: pointer;
}
.fa-leaf:hover {
    background: rgba(99, 102, 241, 0.07);
}
.fa-leaf:focus-visible {
    outline: 2px solid var(--bs-primary);
    outline-offset: -2px;
}
</style>
