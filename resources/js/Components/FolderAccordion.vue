<script setup lang="ts">
import { ref, computed } from 'vue';

interface Folder {
    id: number;
    name: string;
    parent_id: number | null;
}

const props = withDefaults(defineProps<{
    folders: Folder[];
    selectedId: number | null;
    openIds: Set<number>;
    parentId?: number | null;
    showHeader?: boolean;
    depth?: number;
}>(), {
    parentId: null,
    showHeader: true,
    depth: 0,
});

const emit = defineEmits<{
    (e: 'select-folder', id: number): void;
    (e: 'new-folder'): void;
}>();

// Locally-toggled branches. A branch is open if the user expanded it OR it is
// an ancestor of the current folder (openIds) and hasn't been collapsed.
const expanded = ref(new Set<number>());
const collapsed = ref(new Set<number>());

// Direct children of this level, folders sorted alphabetically.
const children = computed(() =>
    props.folders
        .filter((f) => f.parent_id === props.parentId)
        .sort((a, b) => a.name.localeCompare(b.name)),
);

function hasChildren(id: number): boolean {
    return props.folders.some((f) => f.parent_id === id);
}

function isOpen(id: number): boolean {
    if (collapsed.value.has(id)) return false;
    return expanded.value.has(id) || props.openIds.has(id);
}

function toggle(id: number): void {
    if (isOpen(id)) {
        collapsed.value = new Set(collapsed.value).add(id);
        const e = new Set(expanded.value); e.delete(id); expanded.value = e;
    } else {
        expanded.value = new Set(expanded.value).add(id);
        const c = new Set(collapsed.value); c.delete(id); collapsed.value = c;
    }
}
</script>

<template>
    <div class="folder-accordion" :class="{ 'd-flex flex-column h-100 p-2': showHeader }">
        <div v-if="showHeader" class="d-flex align-items-center justify-content-between px-1 mb-1">
            <span class="fw-semibold small text-uppercase text-muted">Folders</span>
            <VibeButton size="sm" variant="secondary" outline title="New folder" aria-label="New folder" data-testid="fa-new" @click="emit('new-folder')">
                <VibeIcon icon="folder-plus" />
            </VibeButton>
        </div>

        <template v-for="folder in children" :key="folder.id">
            <div
                class="fa-row d-flex align-items-center gap-1"
                :class="{ active: selectedId === folder.id }"
                :style="{ paddingLeft: 0.25 + depth * 0.85 + 'rem' }"
                :data-folder="folder.id"
                role="button"
                tabindex="0"
                @click="emit('select-folder', folder.id)"
                @keydown.enter.prevent="emit('select-folder', folder.id)"
                @keydown.space.prevent="emit('select-folder', folder.id)"
            >
                <button
                    v-if="hasChildren(folder.id)"
                    type="button"
                    class="fa-chevron border-0 bg-transparent p-0 text-muted flex-shrink-0"
                    :aria-label="isOpen(folder.id) ? 'Collapse' : 'Expand'"
                    :aria-expanded="isOpen(folder.id)"
                    @click.stop="toggle(folder.id)"
                >
                    <VibeIcon :icon="isOpen(folder.id) ? 'chevron-down' : 'chevron-right'" />
                </button>
                <span v-else class="fa-chevron-spacer flex-shrink-0" />
                <VibeIcon :icon="isOpen(folder.id) ? 'folder2-open' : 'folder2'" class="text-warning flex-shrink-0" />
                <span class="text-truncate">{{ folder.name }}</span>
            </div>

            <FolderAccordion
                v-if="hasChildren(folder.id) && isOpen(folder.id)"
                :folders="folders"
                :selected-id="selectedId"
                :open-ids="openIds"
                :parent-id="folder.id"
                :show-header="false"
                :depth="depth + 1"
                @select-folder="emit('select-folder', $event)"
                @new-folder="emit('new-folder')"
            />
        </template>
    </div>
</template>

<style scoped>
.fa-row {
    cursor: pointer;
    min-width: 0;
    border-radius: 0.25rem;
    padding-top: 0.15rem;
    padding-bottom: 0.15rem;
    padding-right: 0.35rem;
    color: var(--bs-body-color);
}
.fa-row:hover {
    background: rgba(99, 102, 241, 0.08);
}
.fa-row.active {
    background: rgba(99, 102, 241, 0.14);
    font-weight: 600;
}
.fa-chevron {
    width: 1rem;
    line-height: 1;
    cursor: pointer;
}
.fa-chevron-spacer {
    width: 1rem;
}
</style>
