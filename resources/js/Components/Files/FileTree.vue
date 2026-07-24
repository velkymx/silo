<script setup lang="ts">
import { computed } from 'vue';
import { iconFor } from '../../lib/fileTypes';

interface FolderRow { id: number; name: string; is_dir: true; starred: boolean; item_count: number; has_children: boolean }
interface FileRow { id: number; name: string; is_dir: false; type?: string; size: number; starred?: boolean; status?: string }
type Children = { folders: FolderRow[]; files: FileRow[] };

const props = withDefaults(defineProps<{
    nodeId: number | null;
    childrenCache: Map<number | null, Children>;
    expanded: Set<number>;
    loading: Set<number>;
    selectedId: number | null;
    selectedSet: Set<number>;
    depth?: number;
}>(), { depth: 0 });

const emit = defineEmits<{
    (e: 'toggle', id: number): void;
    (e: 'select', item: FileRow): void;
    (e: 'open', item: FileRow): void;
    (e: 'check', id: number): void;
}>();

const children = computed<Children>(() => props.childrenCache.get(props.nodeId) ?? { folders: [], files: [] });
const folderIndent = computed(() => ({ paddingLeft: `${(props.depth ?? 0) * 1.1 + 0.25}rem` }));
const fileIndent = computed(() => ({ paddingLeft: `${(props.depth ?? 0) * 1.1 + 1.35}rem` }));
</script>

<template>
    <div class="file-tree">
        <template v-for="f in children.folders" :key="'d' + f.id">
            <div
                class="ft-row ft-folder d-flex align-items-center gap-1"
                :class="{ 'ft-row--selected': selectedId === f.id }"
                :style="folderIndent"
                :data-tree-folder="f.id"
                role="button"
                @click="emit('toggle', f.id)"
            >
                <VibeIcon :icon="expanded.has(f.id) ? 'caret-down-fill' : 'caret-right-fill'" class="ft-caret" />
                <VibeIcon :icon="expanded.has(f.id) ? 'folder2-open' : 'folder2'" class="ft-icon" />
                <span class="text-truncate flex-grow-1">{{ f.name }}</span>
                <VibeSpinner v-if="loading.has(f.id)" size="sm" />
                <span v-else-if="f.item_count" class="badge text-bg-light ms-1 flex-shrink-0">{{ f.item_count }}</span>
            </div>
            <FileTree
                v-if="expanded.has(f.id)"
                :node-id="f.id"
                :children-cache="childrenCache"
                :expanded="expanded"
                :loading="loading"
                :selected-id="selectedId"
                :selected-set="selectedSet"
                :depth="(depth ?? 0) + 1"
                @toggle="emit('toggle', $event)"
                @select="emit('select', $event)"
                @open="emit('open', $event)"
                @check="emit('check', $event)"
            />
        </template>

        <div
            v-for="file in children.files"
            :key="'f' + file.id"
            class="ft-row ft-file d-flex align-items-center gap-1"
            :class="{ 'ft-row--selected': selectedId === file.id }"
            :style="fileIndent"
            :data-tree-file="file.id"
            role="button"
            @click="emit('select', file)"
            @dblclick="emit('open', file)"
        >
            <VibeFormCheckbox
                class="ft-check"
                :model-value="selectedSet.has(file.id)"
                @update:model-value="emit('check', file.id)"
                @click.stop
            />
            <VibeIcon :icon="iconFor(file.type)" class="ft-icon" />
            <span class="text-truncate flex-grow-1">{{ file.name }}</span>
            <VibeIcon v-if="file.starred" icon="star-fill" class="text-warning flex-shrink-0" />
        </div>
    </div>
</template>

<style scoped>
.ft-row {
    padding-top: 0.28rem;
    padding-bottom: 0.28rem;
    padding-right: 0.5rem;
    cursor: pointer;
    border-radius: var(--radius-sm, 0.35rem);
}
.ft-row:hover { background: rgba(var(--bs-primary-rgb), 0.06); }
.ft-row--selected { background: rgba(var(--bs-primary-rgb), 0.12); }
.ft-caret { width: 0.85rem; font-size: 0.7rem; color: var(--bs-secondary-color); flex-shrink: 0; }
.ft-icon { flex-shrink: 0; }
.ft-check { opacity: 0; }
.ft-row:hover .ft-check { opacity: 1; }
</style>
