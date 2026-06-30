<script setup lang="ts">
import { ref, computed } from 'vue';

interface Folder { id: number; name: string; parent_id: number | null }

const props = defineProps<{
    folders: Folder[];
    rootId: number | null;
    selectedFolder: number | null;
}>();

const emit = defineEmits<{
    (e: 'select-folder', id: number | null): void;
    (e: 'new-folder'): void;
}>();

const expanded = ref(new Set<number>());

const childrenByParent = computed(() => {
    const map = new Map<number | null, Folder[]>();
    for (const f of props.folders) {
        const key = f.parent_id;
        if (!map.has(key)) map.set(key, []);
        map.get(key)!.push(f);
    }
    for (const list of map.values()) list.sort((a, b) => a.name.localeCompare(b.name));
    return map;
});

function toggle(id: number): void {
    const next = new Set(expanded.value);
    next.has(id) ? next.delete(id) : next.add(id);
    expanded.value = next;
}

interface VisibleRow { id: number; name: string; depth: number; hasChildren: boolean }

// Walk the tree from BOTH the root folder and any top-level (parent_id ===
// null) folders. Folders the user created via "New folder" without selecting
// a parent land at the top level; the older code only walked from
// props.rootId and they were lost.
const visibleRows = computed<VisibleRow[]>(() => {
    const rows: VisibleRow[] = [];
    const seen = new Set<number>();
    const walk = (parentId: number | null, depth: number) => {
        for (const folder of childrenByParent.value.get(parentId) ?? []) {
            if (seen.has(folder.id)) continue;
            seen.add(folder.id);
            const hasChildren = childrenByParent.value.has(folder.id);
            rows.push({ id: folder.id, name: folder.name, depth, hasChildren });
            if (hasChildren && expanded.value.has(folder.id)) walk(folder.id, depth + 1);
        }
    };
    walk(null, 0);
    if (props.rootId !== null) walk(props.rootId, 0);
    return rows;
});
</script>

<template>
    <div class="notes-folders d-flex flex-column h-100 border-end bg-body-tertiary p-2">
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
            <VibeIcon icon="journals" />
            <span>All Notes</span>
        </button>

        <button
            v-for="row in visibleRows"
            :key="row.id"
            type="button"
            class="side-row w-100 text-start d-flex align-items-center gap-1 px-2 py-1 rounded border-0 bg-transparent"
            :class="{ active: selectedFolder === row.id }"
            :style="{ paddingLeft: 0.5 + row.depth * 0.85 + 'rem' }"
            @click="emit('select-folder', row.id)"
        >
            <button
                v-if="row.hasChildren"
                type="button"
                class="chevron text-muted p-0 border-0 bg-transparent"
                :aria-label="expanded.has(row.id) ? 'Collapse' : 'Expand'"
                :aria-expanded="expanded.has(row.id)"
                @click.stop="toggle(row.id)"
            >
                <VibeIcon :icon="expanded.has(row.id) ? 'chevron-down' : 'chevron-right'" />
            </button>
            <span v-else class="chevron-spacer" />
            <VibeIcon :icon="expanded.has(row.id) ? 'folder2-open' : 'folder2'" class="text-warning" />
            <span class="text-truncate">{{ row.name }}</span>
        </button>
    </div>
</template>

<style scoped>
.chevron {
    width: 1rem;
    flex-shrink: 0;
}
.chevron-spacer {
    width: 1rem;
    flex-shrink: 0;
}
.chevron {
    cursor: pointer;
}
</style>
