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

// VibeAccordion is data-driven: one item per child folder. `show` auto-opens
// ancestors of the current folder.
const items = computed(() =>
    children.value.map((f) => ({
        id: String(f.id),
        title: f.name,
        content: '',
        show: props.openIds.has(f.id),
        icon: f.icon ?? null,
    })),
);

function folderId(itemId: string): number {
    return Number(itemId);
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

        <VibeAccordion
            v-if="items.length"
            flush
            always-open
            :items="items"
            @item-click="emit('select-folder', folderId($event.item.id))"
        >
            <template #title="{ item }">
                <span :data-folder="item.id" class="d-flex align-items-center flex-grow-1 min-w-0" :class="{ 'fw-semibold': selectedId === folderId(item.id) }">
                    <VibeIcon :icon="item.icon ?? (openIds.has(folderId(item.id)) ? 'folder2-open' : 'folder2')" class="me-2" />
                    <span class="text-truncate flex-grow-1">{{ item.title }}</span>
                    <span v-if="counts && counts[folderId(item.id)] != null" class="badge text-bg-light ms-2 flex-shrink-0">{{ counts[folderId(item.id)] }}</span>
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
                    :counts="counts"
                    @select-folder="emit('select-folder', $event)"
                    @new-folder="emit('new-folder')"
                />
            </template>
        </VibeAccordion>
    </div>
</template>

<style scoped>
.min-w-0 {
    min-width: 0;
}
</style>
