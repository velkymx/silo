<script setup>
import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    folders: { type: Array, default: () => [] },
    parentId: { type: Number, default: null },
    currentId: { type: Number, default: null },
    openIds: { type: Object, default: () => new Set() },
});

const children = computed(() =>
    props.folders
        .filter((f) => (f.parent_id ?? null) === props.parentId)
        .sort((a, b) => a.name.localeCompare(b.name))
);

const open = ref(new Set(props.openIds));

// Keep ancestors of the current folder expanded as the user navigates.
watch(() => props.openIds, (ids) => {
    for (const id of ids) open.value.add(id);
    open.value = new Set(open.value);
});

function hasChildren(id) {
    return props.folders.some((f) => f.parent_id === id);
}
function toggle(id) {
    open.value.has(id) ? open.value.delete(id) : open.value.add(id);
    open.value = new Set(open.value);
}
function go(id) {
    router.get('/', id ? { folder: id } : {}, { preserveScroll: true });
}
// Drag a file/folder onto a sidebar folder to move it there.
function onDrop({ payload }, folderId) {
    if (!payload || payload.id === folderId) return;
    router.post(`/files/${payload.id}/move`, { target_id: folderId }, { preserveScroll: true });
}
</script>

<template>
    <ul class="folder-tree nav nav-pills flex-column list-unstyled mb-0">
        <li v-for="child in children" :key="child.id" class="nav-item">
            <VibeDroppable group="files" tag="div" @drop="onDrop($event, child.id)">
                <template #default="drop">
                    <div
                        class="ft-row nav-link d-flex align-items-center"
                        :class="{ active: child.id === currentId, 'drop-over': drop && drop.isOver }"
                        @click="go(child.id)"
                    >
                        <button
                            v-if="hasChildren(child.id)"
                            class="ft-toggle btn btn-link btn-sm p-0"
                            type="button"
                            @click.stop="toggle(child.id)"
                        >
                            <VibeIcon :icon="open.has(child.id) ? 'chevron-down' : 'chevron-right'" />
                        </button>
                        <span v-else class="ft-toggle"></span>

                        <VibeIcon
                            :icon="open.has(child.id) ? 'folder2-open' : 'folder-fill'"
                            class="text-warning me-2 flex-shrink-0"
                        />
                        <span class="text-truncate">{{ child.name }}</span>
                    </div>
                </template>
            </VibeDroppable>

            <div v-if="open.has(child.id)" class="ft-children">
                <FolderTree
                    :folders="folders"
                    :parent-id="child.id"
                    :current-id="currentId"
                    :open-ids="openIds"
                />
            </div>
        </li>
    </ul>
</template>

<style scoped>
/* Row uses Bootstrap .nav-pills .nav-link (+ .active) styling for consistency
   with the rest of the sidebar; only tighten the padding for a tree feel. */
.ft-row {
    padding: 0.3rem 0.5rem;
    cursor: pointer;
    user-select: none;
    font-size: 0.9rem;
}
.ft-row.drop-over {
    outline: 2px dashed var(--bs-primary);
    outline-offset: -2px;
}
.ft-toggle {
    width: 1.1rem;
    flex-shrink: 0;
    color: var(--bs-secondary-color);
    text-decoration: none;
    display: inline-flex;
    justify-content: center;
}
/* Indent guide for nested levels. */
.ft-children {
    margin-left: 0.65rem;
    padding-left: 0.5rem;
    border-left: 1px solid var(--bs-border-color);
}
</style>
