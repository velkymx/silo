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
// ancestors of the current folder.
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

        <VibeAccordion
            v-if="items.length"
            flush
            always-open
            :items="items"
            @item-click="emit('select-folder', folderId($event.item.id))"
        >
            <template #title="{ item }">
                <span :data-folder="item.id" :class="{ 'fw-semibold': selectedId === folderId(item.id) }">
                    <VibeIcon :icon="openIds.has(folderId(item.id)) ? 'folder2-open' : 'folder2'" class="me-2" />{{ item.title }}
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
