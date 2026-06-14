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
</script>

<template>
    <ul class="list-unstyled mb-0">
        <li v-for="child in children" :key="child.id" class="py-1">
            <div class="d-flex align-items-center">
                <VibeButton
                    v-if="hasChildren(child.id)"
                    variant="link"
                    size="sm"
                    class="p-0 me-1 text-muted"
                    @click="toggle(child.id)"
                >
                    <VibeIcon :icon="open.has(child.id) ? 'chevron-down' : 'chevron-right'" />
                </VibeButton>
                <span v-else class="me-1 d-inline-block" style="width: 1rem"></span>
                <VibeButton
                    variant="link"
                    class="p-0 text-decoration-none text-truncate"
                    :class="child.id === currentId ? 'fw-bold' : 'text-body'"
                    @click="go(child.id)"
                >
                    <VibeIcon
                        :icon="open.has(child.id) ? 'folder2-open' : 'folder-fill'"
                        class="text-warning me-1"
                    />{{ child.name }}
                </VibeButton>
            </div>
            <div v-if="open.has(child.id)" class="ps-3">
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
