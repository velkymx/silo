<script setup>
import { ref, onMounted, watch } from 'vue';
import { router } from '@inertiajs/vue3';

const props = defineProps({
    // The folder node, or null for the synthetic root (renders no row).
    folder: { type: Object, default: null },
    level: { type: Number, default: 0 },
    currentId: { type: Number, default: null },
    revealIds: { type: Object, default: () => new Set() },
});

const isRoot = props.folder === null;
const expanded = ref(isRoot);
const loaded = ref(false);
const loading = ref(false);
const children = ref([]);

async function load() {
    if (loaded.value || loading.value) return;
    loading.value = true;
    try {
        const url = isRoot ? '/tree' : `/tree/${props.folder.id}`;
        const { data } = await window.axios.get(url);
        children.value = data;
        loaded.value = true;
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    if (isRoot || props.revealIds.has(props.folder?.id)) { expanded.value = true; load(); }
});

watch(() => props.revealIds, (ids) => {
    if (!isRoot && ids.has(props.folder.id) && !expanded.value) { expanded.value = true; load(); }
});

function toggle(e) {
    e?.stopPropagation();
    expanded.value = !expanded.value;
    if (expanded.value) load();
}
function onRow() {
    if (!expanded.value) { expanded.value = true; load(); }
    router.get('/', { folder: props.folder.id }, { preserveScroll: true });
}
function openFile(node) {
    router.get('/', { ...(node.parent_id ? { folder: node.parent_id } : {}), open: node.id }, { preserveScroll: true });
}

const folders = () => children.value.filter((c) => c.is_dir);
const files = () => children.value.filter((c) => !c.is_dir);
const hasChildren = ref(true); // assume expandable until proven empty after load

const imageTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg'];
const videoTypes = ['mp4', 'mov', 'webm', 'mkv'];
const audioTypes = ['mp3', 'wav', 'flac', 'ogg'];

const fileIcon = (type) => {
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
const fileColor = (type) => {
    if (imageTypes.includes(type)) return '#10b981';
    if (type === 'pdf') return '#ef4444';
    if (videoTypes.includes(type)) return '#6366f1';
    if (audioTypes.includes(type)) return '#ec4899';
    if (['zip', 'rar', '7z', 'tar', 'gz'].includes(type)) return '#f59e0b';
    if (['xls', 'xlsx', 'csv', 'ods'].includes(type)) return '#22c55e';
    if (['doc', 'docx', 'odt', 'rtf', 'md', 'markdown', 'txt', 'log'].includes(type)) return '#3b82f6';
    return '#6b7280';
};
const padFor = (lvl, leaf) => ({ paddingLeft: (0.3 + lvl * 0.8 + (leaf ? 1.05 : 0)) + 'rem' });
</script>

<template>
    <!-- A folder's own row -->
    <div
        v-if="!isRoot"
        class="vs-row folder"
        :class="{ active: folder.id === currentId }"
        :style="padFor(level, false)"
        @click="onRow"
    >
        <span class="vs-twisty" @click="toggle">
            <VibeIcon :icon="expanded ? 'chevron-down' : 'chevron-right'" />
        </span>
        <VibeIcon :icon="expanded ? 'folder2-open' : 'folder-fill'" class="vs-icon" :style="{ color: '#f59e0b' }" />
        <span class="text-truncate">{{ folder.name }}</span>
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
            @click="openFile(f)"
        >
            <VibeIcon :icon="fileIcon(f.type)" class="vs-icon" :style="{ color: fileColor(f.type) }" />
            <span class="text-truncate">{{ f.name }}</span>
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
    justify-content: center;
    color: var(--bs-secondary-color);
    font-size: 0.7rem;
    flex-shrink: 0;
}
.vs-icon {
    flex-shrink: 0;
}
</style>
