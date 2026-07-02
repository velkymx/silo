<script setup lang="ts">
import { computed } from 'vue';

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
}>(), {
    parentId: null,
    showHeader: true,
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

// VibeAccordion is data-driven: one item per child folder. `show` auto-opens
// ancestors of the current folder. `title`/`content` are rendered via slots.
const items = computed(() =>
    children.value.map((f) => ({
        id: String(f.id),
        title: f.name,
        content: '',
        show: props.openIds.has(f.id),
    })),
);

function folderId(itemId: string): number {
    return Number(itemId);
}
</script>

<template>
    <div class="folder-accordion w-100" :class="{ 'd-flex flex-column h-100 p-2': showHeader }">
        <div v-if="showHeader" class="d-flex align-items-center justify-content-between px-1 mb-1">
            <span class="fw-semibold small text-uppercase text-muted">Folders</span>
            <VibeButton size="sm" variant="secondary" outline title="New folder" aria-label="New folder" data-testid="fa-new" @click="emit('new-folder')">
                <VibeIcon icon="folder-plus" />
            </VibeButton>
        </div>

        <VibeAccordion v-if="items.length" flush always-open :items="items" class="w-100">
            <template #title="{ item }">
                <span
                    class="fa-row d-flex align-items-center gap-2 flex-grow-1"
                    :class="{ active: selectedId === folderId(item.id) }"
                    :data-folder="item.id"
                    role="button"
                    tabindex="0"
                    @click.stop="emit('select-folder', folderId(item.id))"
                    @keydown.enter.prevent="emit('select-folder', folderId(item.id))"
                    @keydown.space.prevent="emit('select-folder', folderId(item.id))"
                >
                    <VibeIcon :icon="openIds.has(folderId(item.id)) ? 'folder2-open' : 'folder2'" />
                    <span class="text-truncate">{{ item.title }}</span>
                </span>
            </template>
            <template #content="{ item }">
                <FolderAccordion
                    v-if="hasChildren(folderId(item.id))"
                    :folders="folders"
                    :selected-id="selectedId"
                    :open-ids="openIds"
                    :parent-id="folderId(item.id)"
                    :show-header="false"
                    @select-folder="emit('select-folder', $event)"
                    @new-folder="emit('new-folder')"
                />
            </template>
        </VibeAccordion>
    </div>
</template>

<style scoped>
.fa-row {
    cursor: pointer;
    min-width: 0;
    border-radius: 0.25rem;
    padding: 0.15rem 0.35rem;
    color: var(--bs-body-color);
}
.fa-row:hover {
    background: rgba(99, 102, 241, 0.08);
}
.fa-row.active {
    background: rgba(99, 102, 241, 0.14);
    font-weight: 600;
}

/* Flatten Bootstrap's accordion chrome into a light folder tree — no cards,
   no heavy button background, minimal padding. */
:deep(.accordion-item) {
    background: transparent;
    border: 0;
}
:deep(.accordion-button) {
    padding: 0.15rem 0.4rem;
    background: transparent;
    box-shadow: none;
    font-size: 0.9rem;
    color: inherit;
}
:deep(.accordion-button:not(.collapsed)) {
    background: transparent;
    color: inherit;
    box-shadow: none;
}
:deep(.accordion-button:focus) {
    box-shadow: none;
}
:deep(.accordion-button::after) {
    width: 0.85rem;
    height: 0.85rem;
    background-size: 0.85rem;
}
:deep(.accordion-body) {
    padding: 0 0 0 0.85rem;
}
</style>
