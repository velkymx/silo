<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import { http } from '../lib/http';

interface TreeNode {
    id: number;
    name: string;
    parent_id: number | null;
    is_dir: boolean;
    type: string;
}

const props = withDefaults(defineProps<{
    // The folder node, or null for the synthetic root (renders no row).
    folder?: TreeNode | null;
    level?: number;
    currentId?: number | null;
    revealIds?: Set<number>;
}>(), {
    folder: null,
    level: 0,
    currentId: null,
    revealIds: () => new Set<number>(),
});

const isRoot = props.folder === null;
const expanded = ref(isRoot);
const loaded = ref(false);
const loading = ref(false);
const children = ref<TreeNode[]>([]);

// Race guard: a stale response (or one that resolves after unmount) must not
// overwrite this node's children.
let loadSeq = 0;
let alive = true;

async function load(): Promise<void> {
    if (loaded.value || loading.value) return;
    loading.value = true;
    const token = ++loadSeq;
    try {
        const url = isRoot ? '/tree' : `/tree/${props.folder!.id}`;
        const result = await http.get<TreeNode[]>(url);
        if (!alive || token !== loadSeq) return;
        children.value = result;
        loaded.value = true;
    } finally {
        if (token === loadSeq) loading.value = false;
    }
}

onBeforeUnmount(() => { alive = false; loadSeq++; });

onMounted(() => {
    if (isRoot || (props.folder && props.revealIds.has(props.folder.id))) { expanded.value = true; load(); }
});

watch(() => props.revealIds, (ids) => {
    if (!isRoot && props.folder && ids.has(props.folder.id) && !expanded.value) { expanded.value = true; load(); }
});

function toggle(e?: Event): void {
    e?.stopPropagation();
    expanded.value = !expanded.value;
    if (expanded.value) load();
}
function onRow(): void {
    if (!expanded.value) { expanded.value = true; load(); }
    router.get('/', { folder: props.folder!.id }, { preserveScroll: true });
}
function openFile(node: TreeNode): void {
    router.get('/', { ...(node.parent_id ? { folder: node.parent_id } : {}), open: node.id }, { preserveScroll: true });
}

const folders = (): TreeNode[] => children.value.filter((c) => c.is_dir);
const files = (): TreeNode[] => children.value.filter((c) => !c.is_dir);

const imageTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
const videoTypes = ['mp4', 'mov', 'webm', 'mkv'];
const audioTypes = ['mp3', 'wav', 'flac', 'ogg'];

const fileIcon = (type: string): string => {
    if (imageTypes.includes(type)) return 'file-earmark-image';
    if (type === 'pdf') return 'file-earmark-pdf';
    if (['zip', 'rar', '7z', 'tar', 'gz'].includes(type)) return 'file-earmark-zip';
    if (['xls', 'xlsx', 'csv', 'ods'].includes(type)) return 'file-earmark-spreadsheet';
    if (['doc', 'docx', 'odt', 'rtf'].includes(type)) return 'file-earmark-word';
    if (['md', 'markdown', 'txt', 'log'].includes(type)) return 'file-earmark-text';
    if (videoTypes.includes(type)) return 'file-earmark-play';
    if (audioTypes.includes(type)) return 'file-earmark-music';
    return 'file-earmark';
};
// Match the datagrid icon colors (Files Index colorFor).
const fileColor = (type: string): string => {
    if (imageTypes.includes(type)) return '#10b981';
    if (type === 'pdf') return '#ef4444';
    if (videoTypes.includes(type)) return '#6366f1';
    if (audioTypes.includes(type)) return '#ec4899';
    if (['zip', 'rar', '7z', 'tar', 'gz'].includes(type)) return '#f59e0b';
    if (['xls', 'xlsx', 'csv', 'ods'].includes(type)) return '#22c55e';
    if (['doc', 'docx', 'odt', 'rtf', 'md', 'markdown', 'txt', 'log'].includes(type)) return '#3b82f6';
    return '#6b7280';
};
const padFor = (lvl: number, leaf: boolean) => ({ paddingLeft: (0.3 + lvl * 0.8 + (leaf ? 1.05 : 0)) + 'rem' });
</script>

<template>
    <!-- A folder's own row -->
    <div
        v-if="folder"
        class="vs-row folder"
        :class="{ active: folder.id === currentId }"
        :style="padFor(level, false)"
        role="treeitem"
        tabindex="0"
        :aria-expanded="expanded"
        :aria-label="folder.name"
        @click="onRow"
        @keydown.enter.prevent="onRow"
        @keydown.space.prevent="toggle()"
    >
        <button
            type="button"
            class="vs-twisty"
            :aria-label="(expanded ? 'Collapse ' : 'Expand ') + folder.name"
            @click="toggle"
            @keydown.enter.stop.prevent="toggle()"
        >
            <VibeIcon :icon="expanded ? 'chevron-down' : 'chevron-right'" />
        </button>
        <VibeIcon :icon="expanded ? 'folder2-open' : 'folder-fill'" class="vs-icon" :style="{ color: '#f59e0b' }" />
        <span class="text-truncate" :title="folder.name">{{ folder.name }}</span>
    </div>

    <!-- Children (lazy) -->
    <template v-if="expanded">
        <FolderTree
            v-for="f in folders()"
            :key="'d' + f.id"
            :folder="f"
            :level="isRoot ? 0 : level + 1"
            :current-id="currentId"
            :reveal-ids="revealIds"
        />
        <div
            v-for="f in files()"
            :key="'f' + f.id"
            class="vs-row file"
            :style="padFor(isRoot ? 0 : level + 1, true)"
            role="treeitem"
            tabindex="0"
            :aria-label="f.name"
            @click="openFile(f)"
            @keydown.enter.prevent="openFile(f)"
        >
            <VibeIcon :icon="fileIcon(f.type)" class="vs-icon" :style="{ color: fileColor(f.type) }" />
            <span class="text-truncate" :title="f.name">{{ f.name }}</span>
        </div>
        <div v-if="loading" class="vs-row text-muted" :style="padFor(isRoot ? 0 : level + 1, true)">
            <VibeSpinner size="sm" class="me-2" />Loading…
        </div>
    </template>
</template>

<style scoped>
.vs-row {
    display: flex;
    align-items: center;
    gap: 0.25rem;
    padding-top: 0.18rem;
    padding-bottom: 0.18rem;
    padding-right: 0.4rem;
    font-size: 0.86rem;
    line-height: 1.5;
    cursor: pointer;
    border-radius: 0.3rem;
    white-space: nowrap;
}
.vs-row:hover {
    background: var(--bs-secondary-bg);
}
.vs-row.active {
    background: rgba(99, 102, 241, 0.14);
    color: #4f46e5;
}
.vs-twisty {
    width: 1rem;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--bs-secondary-color);
    font-size: 0.7rem;
    flex-shrink: 0;
    padding: 0;
    border: 0;
    background: none;
    line-height: 1;
}
.vs-icon {
    flex-shrink: 0;
}
</style>
