<script setup lang="ts">
import { ref, computed } from 'vue';

const props = defineProps<{
    tags: { id: number; name: string; color?: string }[];
    activeTag: string | null;
}>();

const emit = defineEmits<{
    (e: 'select-tag', path: string | null): void;
}>();

const expandedTags = ref(new Set<string>());

// Build a nested tag tree by splitting each "#parent/child" tag on '/'.
const tagTree = computed(() => {
    const root: { children: Map<string, { label: string; path: string; id: number | null; color: string | null; children: Map<string, any> }> } = { children: new Map() };
    for (const tag of props.tags) {
        const parts = tag.name.split('/');
        let node = root;
        let path = '';
        parts.forEach((part, i) => {
            path = i === 0 ? part : `${path}/${part}`;
            if (!node.children.has(part)) {
                node.children.set(part, { label: part, path, id: null, color: null, children: new Map() });
            }
            const next = node.children.get(part)!;
            node = next;
            if (i === parts.length - 1) {
                node.id = tag.id;
                node.color = tag.color ?? null;
            }
        });
    }
    return root;
});

interface TagRow { path: string; label: string; depth: number; hasChildren: boolean; color: string | null }

const visibleTagRows = computed<TagRow[]>(() => {
    const rows: TagRow[] = [];
    const walk = (node: { children: Map<string, any> }, depth: number) => {
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

function toggleTag(path: string): void {
    const next = new Set(expandedTags.value);
    next.has(path) ? next.delete(path) : next.add(path);
    expandedTags.value = next;
}
</script>

<template>
    <div class="notes-sidebar d-flex flex-column h-100 border-end bg-body-tertiary p-2">
        <div class="d-flex align-items-center justify-content-between px-1 mb-1">
            <span class="fw-semibold small text-uppercase text-muted">Tags</span>
        </div>

        <button
            type="button"
            class="side-row w-100 text-start d-flex align-items-center gap-2 px-2 py-1 rounded border-0 bg-transparent"
            :class="{ active: activeTag === null }"
            @click="emit('select-tag', null)"
        >
            <VibeIcon icon="tags" />
            <span>All tags</span>
        </button>

        <button
            v-for="r in visibleTagRows"
            :key="r.path"
            type="button"
            class="side-row w-100 text-start d-flex align-items-center gap-1 px-2 py-1 rounded border-0 bg-transparent"
            :class="{ active: activeTag === r.path }"
            :style="{ paddingLeft: 0.5 + r.depth * 0.85 + 'rem' }"
            @click="emit('select-tag', r.path)"
        >
            <button
                v-if="r.hasChildren"
                type="button"
                class="chevron text-muted p-0 border-0 bg-transparent"
                :aria-label="expandedTags.has(r.path) ? 'Collapse' : 'Expand'"
                :aria-expanded="expandedTags.has(r.path)"
                @click.stop="toggleTag(r.path)"
            >
                <VibeIcon :icon="expandedTags.has(r.path) ? 'chevron-down' : 'chevron-right'" />
            </button>
            <span v-else class="chevron-spacer" />
            <VibeIcon icon="tags" :style="r.color ? { color: r.color } : null" />
            <span class="text-truncate">{{ r.label }}</span>
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
