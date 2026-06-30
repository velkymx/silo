<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    folders: { type: Array, default: () => [] },
    rootId: { type: Number, default: null },
    tags: { type: Array, default: () => [] },
    // The active tag is a full path string ("work/projects"), or null.
    activeTag: { type: String, default: null },
    selectedFolder: { type: Number, default: null },
});
const emit = defineEmits(['select-folder', 'select-tag', 'new-folder']);

const expanded = ref(new Set());
const expandedTags = ref(new Set());

const childrenByParent = computed(() => {
    const map = new Map();
    for (const f of props.folders) {
        const key = f.parent_id;
        if (!map.has(key)) map.set(key, []);
        map.get(key).push(f);
    }
    for (const list of map.values()) list.sort((a, b) => a.name.localeCompare(b.name));
    return map;
});

// Flatten the tree to the rows currently visible (children shown only when
// every ancestor is expanded), each tagged with its depth + whether it has
// children — so the template can render indentation and a chevron.
const visibleRows = computed(() => {
    const rows = [];
    // Walk from BOTH the root folder and any top-level (parent_id === null)
    // folders. Folders the user created via "New folder" without selecting a
    // parent land at the top level; the older code only walked from
    // props.rootId and they were lost.
    const seen = new Set();
    const walk = (parentId, depth) => {
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

function toggle(id) {
    const next = new Set(expanded.value);
    next.has(id) ? next.delete(id) : next.add(id);
    expanded.value = next;
}

// Build a nested tag tree by splitting each "#parent/child" tag on '/'.
// Intermediate segments become grouping nodes even when no exact tag exists.
const tagTree = computed(() => {
    const root = { children: new Map() };
    for (const tag of props.tags) {
        const parts = tag.name.split('/');
        let node = root;
        let path = '';
        parts.forEach((part, i) => {
            path = i === 0 ? part : `${path}/${part}`;
            if (!node.children.has(part)) {
                node.children.set(part, { label: part, path, id: null, color: null, children: new Map() });
            }
            node = node.children.get(part);
            if (i === parts.length - 1) {
                node.id = tag.id;
                node.color = tag.color;
            }
        });
    }
    return root;
});

const visibleTagRows = computed(() => {
    const rows = [];
    const walk = (node, depth) => {
        const kids = [...node.children.values()].sort((a, b) => a.label.localeCompare(b.label));
        for (const k of kids) {
            const hasChildren = k.children.size > 0;
            rows.push({ path: k.path, label: k.label, depth, hasChildren, color: k.color });
            if (hasChildren && expandedTags.value.has(k.path)) walk(k, depth + 1);
        }
    };
    walk(tagTree.value, 0);
    return rows;
});

function toggleTag(path) {
    const next = new Set(expandedTags.value);
    next.has(path) ? next.delete(path) : next.add(path);
    expandedTags.value = next;
}
</script>

<template>
    <div class="notes-sidebar d-flex flex-column h-100 border-end bg-body-tertiary p-2">
        <div class="d-flex align-items-center justify-content-between px-1 mb-1">
            <span class="fw-semibold small text-uppercase text-muted">Folders</span>
            <VibeButton size="sm" variant="light" title="New folder" aria-label="New folder" @click="emit('new-folder')">
                <VibeIcon icon="folder-plus" />
            </VibeButton>
        </div>

        <button
            type="button"
            class="side-row w-100 text-start d-flex align-items-center gap-2 px-2 py-1 rounded border-0 bg-transparent"
            :class="{ active: selectedFolder === null && activeTag === null }"
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
            <VibeButton
                v-if="row.hasChildren"
                variant="link"
                size="sm"
                class="chevron text-muted p-0 border-0"
                :aria-label="expanded.has(row.id) ? 'Collapse' : 'Expand'"
                :aria-expanded="expanded.has(row.id)"
                @click.stop="toggle(row.id)"
            >
                <VibeIcon :icon="expanded.has(row.id) ? 'chevron-down' : 'chevron-right'" />
            </VibeButton>
            <span v-else class="chevron-spacer"></span>
            <VibeIcon :icon="expanded.has(row.id) && row.hasChildren ? 'folder2-open' : 'folder2'" class="text-warning" />
            <span class="text-truncate">{{ row.name }}</span>
        </button>

        <div v-if="tags.length" class="side-heading text-uppercase small text-muted fw-semibold px-2 mt-3 mb-1">
            Tags
        </div>
        <button
            v-for="row in visibleTagRows"
            :key="row.path"
            type="button"
            class="side-row w-100 text-start d-flex align-items-center gap-1 px-2 py-1 rounded border-0 bg-transparent"
            :class="{ active: activeTag === row.path }"
            :style="{ paddingLeft: 0.5 + row.depth * 0.85 + 'rem' }"
            @click="emit('select-tag', row.path)"
        >
            <VibeIcon
                v-if="row.hasChildren"
                :icon="expandedTags.has(row.path) ? 'chevron-down' : 'chevron-right'"
                class="chevron text-muted"
                @click.stop="toggleTag(row.path)"
            />
            <span v-else class="chevron-spacer"></span>
            <VibeIcon icon="hash" :style="row.color ? { color: row.color } : {}" />
            <span class="text-truncate">{{ row.label }}</span>
        </button>
    </div>
</template>

<style scoped>
.notes-sidebar {
    width: 230px;
    flex-shrink: 0;
    overflow-y: auto;
}
.chevron,
.chevron-spacer {
    width: 1rem;
    flex-shrink: 0;
}
.chevron {
    cursor: pointer;
}
</style>
