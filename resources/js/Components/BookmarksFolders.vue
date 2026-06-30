<script setup lang="ts">
defineProps<{
    folders: string[];
    counts: Record<string, number>;
    selectedFolder: string | null;
}>();

const emit = defineEmits<{
    (e: 'select-folder', folder: string | null): void;
    (e: 'new-folder'): void;
}>();
</script>

<template>
    <div class="bookmarks-folders d-flex flex-column p-2 border-end bg-body-tertiary h-100">
        <div class="d-flex align-items-center justify-content-between px-1 mb-1">
            <span class="fw-semibold small text-uppercase text-muted">Folders</span>
            <VibeButton size="sm" variant="light" title="New folder" aria-label="New folder" @click="emit('new-folder')">
                <VibeIcon icon="folder-plus" />
            </VibeButton>
        </div>
        <button
            type="button"
            class="side-row w-100 text-start d-flex align-items-center gap-2 px-2 py-1 rounded border-0 bg-transparent"
            :class="{ active: selectedFolder === null }"
            @click="emit('select-folder', null)"
        >
            <VibeIcon icon="bookmark-fill" />
            <span class="flex-grow-1">All Bookmarks</span>
        </button>
        <button
            v-for="folder in folders"
            :key="folder"
            type="button"
            class="side-row w-100 text-start d-flex align-items-center gap-2 px-2 py-1 rounded border-0 bg-transparent"
            :class="{ active: selectedFolder === folder }"
            @click="emit('select-folder', folder)"
        >
            <VibeIcon :icon="folder === 'General' ? 'folder' : 'folder-fill'" class="text-warning" />
            <span class="flex-grow-1 text-truncate">{{ folder }}</span>
            <span class="badge text-bg-light">{{ counts[folder] ?? 0 }}</span>
        </button>
    </div>
</template>
