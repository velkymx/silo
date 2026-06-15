<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import FolderTree from '../../Components/FolderTree.vue';
import FileItem from '../../Components/FileItem.vue';
import ItemActions from '../../Components/ItemActions.vue';
import AdvancedSearchModal from '../../Components/AdvancedSearchModal.vue';
import UploadModal from '../../Components/UploadModal.vue';
import ShareModal from '../../Components/ShareModal.vue';
import EditorModal from '../../Components/EditorModal.vue';
import RenameModal from '../../Components/RenameModal.vue';
import MarkdownViewer from '../../Components/MarkdownViewer.vue';
import DocViewer from '../../Components/DocViewer.vue';
import { useSelection } from '../../composables/useSelection';
import { useBatchRename } from '../../composables/useBatchRename';
import { useJobPolling } from '../../composables/useJobPolling';
import { useBusyGuard } from '../../composables/useBusyGuard';
import { useQuickLook } from '../../composables/useQuickLook';
import { descendantIds } from '../../lib/folderTree';
import { useStorageMeter } from '../../composables/useStorageMeter';
import EmptyState from '../../Components/EmptyState.vue';
import { useConfirm } from '../../composables/useConfirm';
import { imageTypes, typeLabel, colorFor, iconFor } from '../../lib/fileTypes';
import { triggerDownload } from '../../lib/download';

const officeTypes = ['docx', 'xlsx', 'xls', 'csv', 'ods'];
// Office formats edited on the full-screen editor page (binary, versioned).
const officeEditTypes = ['docx', 'xlsx', 'xls', 'csv', 'ods'];
const previewMarkdownTypes = ['md', 'markdown'];

const props = defineProps({
    folders: { type: Array, default: () => [] },
    files: { type: Array, default: () => [] },
    current: { type: Object, default: null },
    breadcrumbs: { type: Array, default: () => [] },
    allFolders: { type: Array, default: () => [] },
    allTags: { type: Array, default: () => [] },
    searching: { type: Boolean, default: false },
    starredOnly: { type: Boolean, default: false },
    recentOnly: { type: Boolean, default: false },
    flat: { type: Boolean, default: false },
    activeTag: { type: Object, default: null },
    storage: { type: Object, default: () => ({ used: 0, quota: 0 }) },
    maxUploadKb: { type: Number, default: 0 },
    filters: { type: Object, default: () => ({ search: '', sort: 'name', direction: 'asc' }) },
});


const { pct: storagePct, bars: storageBars } = useStorageMeter(computed(() => props.storage));

const currentId = computed(() => props.current?.id ?? null);

// IDs along the path from root to the current folder — used to auto-expand the tree.
const ancestorIds = computed(() => {
    const byId = Object.fromEntries(props.allFolders.map((f) => [f.id, f]));
    const ids = new Set();
    let id = currentId.value;
    while (id) {
        ids.add(id);
        id = byId[id]?.parent_id ?? null;
    }
    return ids;
});

// ----- Breadcrumb -----
const breadcrumbItems = computed(() => [
    { text: 'Home', folder: null, active: !props.current },
    ...props.breadcrumbs.map((b, i) => ({
        text: b.name,
        folder: b.id,
        active: i === props.breadcrumbs.length - 1,
    })),
]);

function visitFolder(id) {
    router.get('/', id ? { folder: id } : {}, { preserveScroll: true });
}

function onBreadcrumb({ item }) {
    if (!item.active) visitFolder(item.folder);
}

// ----- Search -----
const search = ref(props.filters.search);

function runSearch() {
    router.get('/', { folder: currentId.value, search: search.value }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function clearSearch() {
    search.value = '';
    router.get('/', currentId.value ? { folder: currentId.value } : {}, { preserveScroll: true });
}

function clearAllFilters() {
    router.get('/', {}, { preserveScroll: true });
}

// All active view filters collapsed into one chip bar (search/tag/starred/recent).
const activeFilters = computed(() => {
    const out = [];
    if (props.searching) out.push({ key: 'search', icon: 'search', label: `Search: "${props.filters.search}"`, clear: clearSearch });
    if (props.activeTag) out.push({ key: 'tag', icon: 'tag-fill', label: `Tag: ${props.activeTag.name}`, clear: () => filterByTag(null) });
    if (props.starredOnly) out.push({ key: 'starred', icon: 'star-fill', label: 'Starred', clear: clearAllFilters });
    if (props.recentOnly) out.push({ key: 'recent', icon: 'clock-history', label: 'Recent', clear: clearAllFilters });
    return out;
});

// ----- Unified Finder/Dropbox-style listing -----
// Folders and files share one list; folders sort ahead of files by default.
const items = computed(() => [
    ...props.folders.map((f) => ({ ...f, is_dir: true, modified: f.updated_at, _sort: 0 })),
    ...props.files.map((f) => ({ ...f, is_dir: false, modified: f.created_at, _sort: 1 })),
]);

const columns = computed(() => [
    { key: 'name', label: 'Name' },
    ...(props.flat ? [{ key: 'location', label: 'Location', sortable: false }] : []),
    { key: 'modified', label: 'Modified' },
    { key: 'size', label: 'Size' },
    { key: 'type', label: 'Type', sortable: false },
    { key: 'actions', label: '', sortable: false, searchable: false },
]);

const versionColumns = [
    { key: 'version', label: 'Version' },
    { key: 'note', label: 'What changed', sortable: false },
    { key: 'size', label: 'Size' },
    { key: 'created_at', label: 'Saved' },
    { key: 'actions', label: '', sortable: false, searchable: false },
];

function toggleStar(item) {
    router.post(`/files/${item.id}/star`, {}, { preserveScroll: true });
}

// Drag-and-drop move: drop a file/folder onto a folder to move it there.
function onDropToFolder({ payload }, folder) {
    if (!payload || !folder?.is_dir || payload.id === folder.id) return;
    router.post(`/files/${payload.id}/move`, { target_id: folder.id }, { preserveScroll: true });
}

// ----- Text / Markdown / HTML editor -----
const markdownTypes = ['md', 'markdown', 'txt', 'text', 'log', 'csv'];
const htmlTypes = ['html', 'htm'];
const editOpen = ref(false);
const editItem = ref(null);
const editKind = ref('markdown');
const editCreating = ref(false);

function isEditable(item) {
    return !item.is_dir && (
        markdownTypes.includes(item.type) ||
        htmlTypes.includes(item.type) ||
        officeEditTypes.includes(item.type)
    );
}

// File menu with Edit hidden for files that can't be edited as text.
function fileMenu(item) {
    return fileActions.filter((a) => a.action !== 'edit' || isEditable(item));
}

async function openEditor(item) {
    if (!isEditable(item)) return;
    // Office docs (docx/xlsx/xls/csv/ods) open in the full-screen editor page.
    if (officeEditTypes.includes(item.type)) {
        router.get(`/files/${item.id}/edit`);
        return;
    }
    editItem.value = item;
    editCreating.value = false;
    editKind.value = htmlTypes.includes(item.type) ? 'html' : 'markdown';
    editOpen.value = true;
}

function openNewMarkdown() {
    editItem.value = null;
    editCreating.value = true;
    editKind.value = 'markdown';
    editOpen.value = true;
}

const newMenu = [
    { text: 'New Folder', action: 'folder', icon: 'folder-plus' },
    { text: 'Markdown file', action: 'markdown', icon: 'markdown' },
    { text: 'Spreadsheet', action: 'xlsx', icon: 'file-earmark-spreadsheet' },
    { text: 'Word document', action: 'docx', icon: 'file-earmark-word' },
    { text: 'Upload files', action: 'upload', icon: 'upload' },
];

function onNewMenu({ item }) {
    if (item.action === 'folder') folderOpen.value = true;
    if (item.action === 'upload') uploadOpen.value = true;
    if (item.action === 'markdown') openNewMarkdown();
    // Blank office docs open the full-screen editor; the file is created on first save.
    if (item.action === 'xlsx' || item.action === 'docx') {
        router.get(`/files/new/${item.action}`, currentId.value ? { folder: currentId.value } : {});
    }
}

// ----- Multi-select + batch operations (Finder-style) -----
function openItem(item) {
    if (item.is_dir) {
        visitFolder(item.id);
    } else {
        quickLook(item);
    }
}

const {
    selectMode, selectedIds, selectedItems, batchIds,
    isSelected, toggleSel, clearSelection, onItemClick,
} = useSelection(items, openItem);

const { confirm } = useConfirm();

// Guards every batch mutation against double-click / double-submit. Only one
// batch op runs at a time, so a single shared single-flight guard is enough.
const { busy: batchBusy, run: runBatch, release: releaseBatch } = useBusyGuard({ autoRelease: false });

async function batchDelete() {
    if (!await confirm({ title: 'Move to trash', message: `Move ${batchIds.value.length} item(s) to trash?`, confirmLabel: 'Move to trash', variant: 'danger' })) return;
    runBatch(() => router.post('/files/batch/delete', { ids: batchIds.value }, {
        preserveScroll: true,
        onSuccess: clearSelection,
        onFinish: releaseBatch,
    }));
}

// New Folder From Selection.
const batchFolderOpen = ref(false);
const batchFolderName = ref('New Folder');
function submitBatchFolder() {
    runBatch(() => router.post('/files/batch/folder', { name: batchFolderName.value, ids: batchIds.value, parent_id: currentId.value }, {
        preserveScroll: true,
        onSuccess: () => { batchFolderOpen.value = false; clearSelection(); },
        onFinish: releaseBatch,
    }));
}

// Batch move.
const batchMoveOpen = ref(false);
const batchMoveTarget = ref('');
function submitBatchMove() {
    runBatch(() => router.post('/files/batch/move', { ids: batchIds.value, target_id: batchMoveTarget.value || null }, {
        preserveScroll: true,
        onSuccess: () => { batchMoveOpen.value = false; clearSelection(); },
        onFinish: releaseBatch,
    }));
}

// Batch rename (find/replace, prefix/suffix, sequential numbering).
const batchRenameOpen = ref(false);
const { renameOpts, renamePreview } = useBatchRename(selectedItems);

function submitBatchRename() {
    const renames = renamePreview.value.map(({ id, to }) => ({ id, name: to }));
    runBatch(() => router.post('/files/batch/rename', { renames }, {
        preserveScroll: true,
        onSuccess: () => { batchRenameOpen.value = false; clearSelection(); },
        onFinish: releaseBatch,
    }));
}

function selectFile(item) {
    if (item.is_dir) return;
    quickSetActive(item);
}

// List vs thumbnail grid view, remembered across visits.
const viewMode = ref(localStorage.getItem('fm-view') === 'grid' ? 'grid' : 'list');
watch(viewMode, (v) => localStorage.setItem('fm-view', v));


const fileActions = [
    { text: 'Download', action: 'download', icon: 'download' },
    { text: 'Edit', action: 'edit', icon: 'pencil-square' },
    { text: 'Details', action: 'details', icon: 'info-circle' },
    { text: 'Share', action: 'share', icon: 'person-plus' },
    { text: 'Tags', action: 'tags', icon: 'tags' },
    { text: 'Versions', action: 'versions', icon: 'clock-history' },
    { text: 'Rename', action: 'rename', icon: 'pencil' },
    { text: 'Move', action: 'move', icon: 'arrows-move' },
    { text: 'Copy', action: 'copy', icon: 'files' },
    { divider: true },
    { text: 'Delete', action: 'delete', icon: 'trash' },
];

const folderActions = [
    { text: 'Share', action: 'share', icon: 'person-plus' },
    { text: 'Tags', action: 'tags', icon: 'tags' },
    { text: 'Rename', action: 'rename', icon: 'pencil' },
    { text: 'Move', action: 'move', icon: 'arrows-move' },
    { text: 'Copy', action: 'copy', icon: 'files' },
    { divider: true },
    { text: 'Delete', action: 'delete', icon: 'trash' },
];

function onAction(item, { item: action }) {
    if (action.action === 'download') triggerDownload(`/download/${item.id}`);
    if (action.action === 'edit') openEditor(item);
    if (action.action === 'details') openDetails(item);
    if (action.action === 'share') openShare(item);
    if (action.action === 'tags') openTags(item);
    if (action.action === 'versions') openVersions(item);
    if (action.action === 'rename') openRename(item);
    if (action.action === 'move') openTransfer(item, 'move');
    if (action.action === 'copy') openTransfer(item, 'copy');
    if (action.action === 'delete') destroy(item);
}

// Run a file action from inside Quick Look: close the preview first so the
// action's own modal isn't stacked on top of it.
function onQuickAction({ item: action }) {
    const target = quickFile.value;
    if (!target) return;
    quickOpen.value = false;
    onAction(target, { item: action });
}

// ----- Versions -----
const versionsOpen = ref(false);
const versionsItem = ref(null);

function openVersions(item) {
    versionsItem.value = item;
    versionsOpen.value = true;
}

async function restoreVersion(version) {
    if (!await confirm({ title: 'Restore version', message: `Restore version ${version.version}? Current content is kept in history.`, confirmLabel: 'Restore' })) return;
    router.post(`/files/${versionsItem.value.id}/versions/${version.id}/restore`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            versionsOpen.value = false;
        },
    });
}

// ----- Details -----
const detailsOpen = ref(false);
const detailsItem = ref(null);

const metadataLabels = {
    width: 'Width',
    height: 'Height',
    camera_make: 'Camera Make',
    camera_model: 'Camera Model',
    taken_at: 'Taken At',
    orientation: 'Orientation',
    title: 'Title',
    artist: 'Artist',
    album: 'Album',
    duration: 'Duration (s)',
    bitrate: 'Bitrate (kbps)',
    sample_rate: 'Sample Rate',
    channels: 'Channels',
    preview: 'Preview',
};

const detailsRows = computed(() => {
    const item = detailsItem.value;
    if (!item) return [];
    const rows = [
        { label: 'Type', value: item.mime || 'folder' },
        { label: 'Size', value: `${(item.size / 1024).toFixed(2)} KB` },
        { label: 'Uploaded', value: item.created_at },
    ];
    if (item.hash) rows.push({ label: 'SHA-256', value: item.hash });
    for (const [key, val] of Object.entries(item.metadata ?? {})) {
        rows.push({ label: metadataLabels[key] ?? key, value: String(val) });
    }
    return rows;
});

function openDetails(item) {
    detailsItem.value = item;
    detailsOpen.value = true;
}

// ----- Quick Look -----
const quickFiles = computed(() => props.files);
const {
    quickOpen, quickIndex, quickFile, selectedIndex,
    setActive: quickSetActive, open: quickLook, openAtSelected: quickOpenSelected,
    step: quickStep, close: quickClose,
} = useQuickLook(quickFiles);

function onKey(e) {
    // Cmd/Ctrl-K focuses the search box from anywhere.
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        document.getElementById('global-search')?.focus();
        return;
    }

    const tag = (e.target?.tagName || '').toLowerCase();
    if (['input', 'textarea', 'select'].includes(tag) || e.target?.isContentEditable) return;

    if (quickOpen.value) {
        if (e.key === 'ArrowRight' || e.key === 'ArrowDown') { e.preventDefault(); quickStep(1); }
        if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') { e.preventDefault(); quickStep(-1); }
        if (e.key === 'Escape') quickClose();
        if (e.key === ' ') { e.preventDefault(); quickClose(); }
        return;
    }

    if (e.key === ' ' && props.files.length) {
        e.preventDefault();
        quickOpenSelected();
    }
}

const imageMime = (f) => f && (f.type ? imageTypes.includes(f.type) : false);

// ----- Upload -----
const uploadOpen = ref(false);

// ----- Create folder -----
const folderOpen = ref(false);
const folderForm = useForm({ folder_name: '', parent_id: currentId.value });

function submitFolder() {
    folderForm.parent_id = currentId.value;
    folderForm.post('/folders', {
        onSuccess: () => {
            folderForm.reset();
            folderOpen.value = false;
        },
    });
}

// ----- Rename -----
const renameOpen = ref(false);
const renameTarget = ref(null);

function openRename(item) {
    renameTarget.value = item;
    renameOpen.value = true;
}

// ----- Move / Copy -----
const transferOpen = ref(false);
const transferMode = ref('move');
const transferItem = ref(null);
const transferForm = useForm({ target_id: null });

const destinationOptions = computed(() => {
    const item = transferItem.value;
    // Folders the item cannot be moved/copied into (itself + its descendants).
    const excluded = item?.is_dir ? descendantIds(item.id, props.allFolders) : new Set();
    const options = [{ value: null, text: 'Home' }];
    for (const f of props.allFolders) {
        if (!excluded.has(f.id)) options.push({ value: f.id, text: f.name });
    }
    return options;
});

function openTransfer(item, mode) {
    transferItem.value = { ...item, is_dir: item.item_count !== undefined };
    transferMode.value = mode;
    transferForm.clearErrors();
    transferForm.target_id = null;
    transferOpen.value = true;
}

function submitTransfer() {
    const url = `/files/${transferItem.value.id}/${transferMode.value}`;
    transferForm.post(url, {
        preserveScroll: true,
        onSuccess: () => {
            transferOpen.value = false;
        },
    });
}

// ----- Share / permissions -----
const shareOpen = ref(false);
const shareTarget = ref(null);

function openShare(item) {
    shareTarget.value = item;
    shareOpen.value = true;
}

// ----- Tags -----
const tagsOpen = ref(false);
const tagsItem = ref(null);
const tagList = ref([]);
const tagInput = ref('');
const tagSaving = ref(false);

const tagSuggestions = computed(() =>
    props.allTags.map((t) => t.name).filter((n) => !tagList.value.includes(n))
);

function openTags(item) {
    tagsItem.value = item;
    tagList.value = (item.tags ?? []).map((t) => t.name);
    tagInput.value = '';
    tagsOpen.value = true;
}

const flashedTag = ref('');
function addTag(name) {
    const value = (name ?? tagInput.value).trim();
    if (value && !tagList.value.includes(value)) {
        tagList.value.push(value);
        // Briefly flash the new badge so the add is visually confirmed.
        flashedTag.value = value;
        setTimeout(() => { if (flashedTag.value === value) flashedTag.value = ''; }, 600);
    }
    tagInput.value = '';
}

function removeTag(name) {
    tagList.value = tagList.value.filter((n) => n !== name);
}

function saveTags() {
    tagSaving.value = true;
    router.put(`/files/${tagsItem.value.id}/tags`, { tags: tagList.value }, {
        preserveScroll: true,
        onSuccess: () => {
            tagsOpen.value = false;
        },
        onFinish: () => {
            tagSaving.value = false;
        },
    });
}

function filterByTag(id) {
    router.get('/', { tag: id }, { preserveScroll: true });
}

// ----- Delete -----
// ----- Advanced search + smart folders (modal owns the form) -----
const advOpen = ref(false);

async function destroy(item) {
    const msg = item.is_dir && item.item_count > 0
        ? `Delete folder "${item.name}" and its ${item.item_count} item(s)? Everything inside moves to trash.`
        : `Move "${item.name}" to trash?`;
    if (!await confirm({ title: 'Move to trash', message: msg, confirmLabel: 'Move to trash', variant: 'danger' })) return;
    router.delete(`/delete/${item.id}`, { preserveScroll: true });
}

// ----- Background-job status polling -----
const filesRef = computed(() => props.files);
const { start: startPolling } = useJobPolling(filesRef, () =>
    router.reload({ only: ['files'], preserveScroll: true })
);

onMounted(() => {
    // Open a file directly when navigated from the sidebar tree (?open=id).
    const openId = new URLSearchParams(window.location.search).get('open');
    if (openId) {
        const f = props.files.find((x) => String(x.id) === openId);
        if (f) quickLook(f);
    }
    startPolling();
    window.addEventListener('keydown', onKey);
});
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKey);
});
</script>

<template>
    <AppLayout>
        <template #sidebar>
            <template v-if="allTags.length">
                <div class="side-heading"><VibeIcon icon="tags-fill" />Tags</div>
                <div class="d-flex flex-wrap gap-1 px-2">
                    <span
                        v-for="tag in allTags"
                        :key="tag.id"
                        class="badge rounded-pill"
                        :style="{
                            backgroundColor: tag.color || '#6c757d',
                            cursor: 'pointer',
                            opacity: activeTag && activeTag.id === tag.id ? 1 : 0.85,
                        }"
                        @click="filterByTag(tag.id)"
                    >{{ tag.name }}</span>
                </div>
            </template>
        </template>

        <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
            <VibeBreadcrumb :items="breadcrumbItems" class="mb-0 text-truncate" @item-click="onBreadcrumb">
                <template #item="{ item, index }">
                    <VibeIcon :icon="index === 0 ? 'house-door-fill' : 'folder-fill'" :class="index === 0 ? '' : 'text-warning'" class="me-1" /><span :title="item.text">{{ item.text }}</span>
                </template>
            </VibeBreadcrumb>
            <div class="d-flex flex-wrap align-items-center gap-2 flex-shrink-0">
                <VibeButton variant="primary" @click="uploadOpen = true">
                    <VibeIcon icon="upload" class="me-1" />Upload
                </VibeButton>
                <VibeDropdown variant="secondary" outline :items="newMenu" @item-click="onNewMenu">
                    <template #button><VibeIcon icon="plus-lg" class="me-1" />New</template>
                    <template #item="{ item }"><VibeIcon :icon="item.icon" class="me-2" />{{ item.text }}</template>
                </VibeDropdown>
                <VibeButton variant="secondary" outline title="Advanced search" @click="advOpen = true">
                    <VibeIcon icon="funnel" class="me-1" />Filters
                </VibeButton>
                <VibeButton
                    :variant="selectMode ? 'primary' : 'secondary'"
                    :outline="!selectMode"
                    title="Select multiple"
                    @click="selectMode ? clearSelection() : (selectMode = true)"
                >
                    <VibeIcon icon="check2-square" class="me-1" />Select
                </VibeButton>
                <div class="vr mx-1 d-none d-sm-block"></div>
                <VibeButtonGroup>
                    <VibeButton
                        :variant="viewMode === 'list' ? 'primary' : 'secondary'"
                        :outline="viewMode !== 'list'"
                        title="List view"
                        @click="viewMode = 'list'"
                    >
                        <VibeIcon icon="list-ul" />
                    </VibeButton>
                    <VibeButton
                        :variant="viewMode === 'grid' ? 'primary' : 'secondary'"
                        :outline="viewMode !== 'grid'"
                        title="Thumbnail view"
                        @click="viewMode = 'grid'"
                    >
                        <VibeIcon icon="grid-3x3-gap-fill" />
                    </VibeButton>
                </VibeButtonGroup>
            </div>
        </div>

        <!-- Batch action bar -->
        <div v-if="selectedIds.size" class="batch-bar d-flex flex-wrap align-items-center gap-2 border rounded bg-body p-2 mb-3 shadow-sm">
            <strong>{{ selectedIds.size }} selected</strong>
            <div class="ms-auto d-flex flex-wrap gap-2">
                <!-- Move is the most common bulk action → the single primary. -->
                <VibeButton variant="primary" size="sm" @click="batchMoveTarget = ''; batchMoveOpen = true">
                    <VibeIcon icon="folder-symlink" class="me-1" />Move…
                </VibeButton>
                <VibeButton variant="secondary" size="sm" outline @click="batchFolderName = 'New Folder'; batchFolderOpen = true">
                    <VibeIcon icon="folder-plus" class="me-1" />New Folder
                </VibeButton>
                <VibeButton variant="secondary" size="sm" outline @click="batchRenameOpen = true">
                    <VibeIcon icon="input-cursor-text" class="me-1" />Rename…
                </VibeButton>
                <VibeButton variant="danger" size="sm" outline :disabled="batchBusy" @click="batchDelete">
                    <VibeIcon icon="trash" class="me-1" />Delete
                </VibeButton>
                <VibeButton variant="secondary" size="sm" outline @click="clearSelection">Clear</VibeButton>
            </div>
        </div>

        <!-- Single compact chip bar for all active view filters. -->
        <VibeAlert v-if="activeFilters.length" variant="light" class="border d-flex flex-wrap align-items-center gap-2 py-2">
            <VibeIcon icon="funnel-fill" class="text-muted" />
            <VibeBadge
                v-for="f in activeFilters"
                :key="f.key"
                variant="secondary"
                class="d-flex align-items-center gap-1"
            >
                <VibeIcon :icon="f.icon" />{{ f.label }}
                <VibeIcon icon="x" style="cursor: pointer" :aria-label="`Clear ${f.label}`" @click="f.clear()" />
            </VibeBadge>
            <VibeButton variant="link" size="sm" class="ms-auto p-0 text-decoration-none" @click="clearAllFilters">Clear all</VibeButton>
        </VibeAlert>

        <!-- Thumbnail / grid view -->
        <VibeRow v-if="viewMode === 'grid'" class="g-3">
            <VibeCol v-for="item in items" :key="item.id" :xs="6" :sm="4" :md="3" :xl="2">
                <FileItem
                    :item="item"
                    view="grid"
                    :select-mode="selectMode"
                    :selected="isSelected(item.id)"
                    :menu="item.is_dir ? folderActions : fileMenu(item)"
                    @open="onItemClick"
                    @toggle-select="toggleSel"
                    @star="toggleStar"
                    @action="onAction(item, $event)"
                    @drop="onDropToFolder"
                />
            </VibeCol>
            <VibeCol v-if="!items.length" :cols="12">
                <EmptyState
                    :icon="flat ? 'search' : 'folder2-open'"
                    :title="flat ? 'No matching files' : 'This folder is empty'"
                    :hint="flat ? 'Try a different search or filter.' : 'Upload files or create a folder to get started.'"
                />
            </VibeCol>
        </VibeRow>

        <VibeDataTable
            v-else
            :items="items"
            :columns="columns"
            row-key="id"
            hover
            :searchable="false"
            :per-page="25"
            :per-page-options="[10, 25, 50, 100]"
            :responsive="false"
            :empty-text="flat ? 'No matching files.' : 'This folder is empty.'"
            @row-clicked="selectFile"
        >
            <template v-if="flat" #cell(location)="{ item }">
                <VibeButton variant="link" class="p-0 text-decoration-none small" @click="visitFolder(item.location?.folder_id)">
                    {{ item.location?.path }}
                </VibeButton>
            </template>

            <template #cell(name)="{ item }">
                <FileItem
                    :item="item"
                    view="list"
                    :select-mode="selectMode"
                    :selected="isSelected(item.id)"
                    @open="onItemClick"
                    @toggle-select="toggleSel"
                    @drop="onDropToFolder"
                    @tag="filterByTag"
                />
            </template>

            <template #cell(modified)="{ item }">
                <span class="text-muted small">{{ item.modified }}</span>
            </template>

            <template #cell(size)="{ item }">
                <span class="text-muted small">
                    {{ item.is_dir ? `${item.item_count} item${item.item_count === 1 ? '' : 's'}` : `${(item.size / 1024).toFixed(1)} KB` }}
                </span>
            </template>

            <template #cell(type)="{ item }">
                <span class="text-muted small">{{ typeLabel(item) }}</span>
            </template>

            <template #cell(actions)="{ item }">
                <div class="d-flex justify-content-end" @click.stop>
                    <ItemActions
                        :item="item"
                        :menu="item.is_dir ? folderActions : fileMenu(item)"
                        @star="toggleStar"
                        @action="onAction(item, $event)"
                    />
                </div>
            </template>
        </VibeDataTable>

        <!-- Editor modal -->
        <EditorModal v-model="editOpen" :item="editItem" :creating="editCreating" :kind="editKind" :parent-id="currentId" />

        <!-- Details modal -->
        <VibeModal v-model="detailsOpen" :title="detailsItem?.name || 'Details'" centered fullscreen hide-footer>
            <table class="table table-sm mb-0">
                <tbody>
                    <tr v-for="row in detailsRows" :key="row.label">
                        <th class="text-nowrap pe-3 text-muted" style="width: 40%">{{ row.label }}</th>
                        <td class="text-break font-monospace small">{{ row.value }}</td>
                    </tr>
                </tbody>
            </table>
        </VibeModal>

        <!-- Share modal -->
        <ShareModal v-model="shareOpen" :item="shareTarget" />

        <!-- Tags modal -->
        <VibeModal v-model="tagsOpen" :title="`Tags — ${tagsItem?.name || ''}`" centered>
            <div class="d-flex flex-wrap gap-2 mb-3">
                <VibeBadge
                    v-for="name in tagList"
                    :key="name"
                    variant="primary"
                    class="d-flex align-items-center"
                    :class="{ 'tag-flash': name === flashedTag }"
                >
                    {{ name }}
                    <VibeIcon icon="x" class="ms-1" style="cursor: pointer" @click="removeTag(name)" />
                </VibeBadge>
                <span v-if="!tagList.length" class="text-muted small">No tags yet.</span>
            </div>
            <div class="d-flex gap-2 align-items-end">
                <div class="flex-grow-1">
                    <VibeAutocomplete
                        v-model="tagInput"
                        :source="tagSuggestions"
                        label="Add a tag"
                        placeholder="Type a tag and press Add"
                        @select="addTag"
                        @keyup.enter="addTag()"
                    />
                </div>
                <VibeButton variant="secondary" @click="addTag()">Add</VibeButton>
            </div>
            <template #footer>
                <VibeButton variant="secondary" outline @click="tagsOpen = false">Cancel</VibeButton>
                <VibeButton variant="primary" :disabled="tagSaving" @click="saveTags"><VibeSpinner v-if="tagSaving" size="sm" class="me-1" />{{ tagSaving ? 'Saving…' : 'Save' }}</VibeButton>
            </template>
        </VibeModal>

        <!-- Versions modal -->
        <VibeModal v-model="versionsOpen" :title="`Versions — ${versionsItem?.name || ''}`" centered fullscreen hide-footer>
            <p class="text-muted small">
                Current: version {{ versionsItem?.version }}.
                <span v-if="!versionsItem?.versions?.length">No previous versions.</span>
            </p>
            <VibeDataTable
                v-if="versionsItem?.versions?.length"
                :items="versionsItem.versions"
                :columns="versionColumns"
                row-key="id"
                hover
                :searchable="false"
                :show-per-page="false"
                :per-page="100"
                :responsive="false"
            >
                <template #cell(version)="{ item }">v{{ item.version }}</template>
                <template #cell(note)="{ item }">
                    <span v-if="item.note" class="small">{{ item.note }}</span>
                    <span v-else class="text-muted fst-italic small">—</span>
                </template>
                <template #cell(size)="{ item }">{{ (item.size / 1024).toFixed(2) }} KB</template>
                <template #cell(created_at)="{ item }"><span class="small">{{ item.created_at }}</span></template>
                <template #cell(actions)="{ item }">
                    <div class="d-flex justify-content-end gap-1">
                        <VibeButton
                            variant="success"
                            size="sm"
                            :href="`/files/${versionsItem.id}/versions/${item.id}/download`"
                            :aria-label="`Download version ${item.version}`"
                        >
                            <VibeIcon icon="download" />
                        </VibeButton>
                        <VibeButton variant="primary" size="sm" outline @click="restoreVersion(item)">
                            <VibeIcon icon="arrow-counterclockwise" class="me-1" />Restore
                        </VibeButton>
                    </div>
                </template>
            </VibeDataTable>
        </VibeModal>

        <!-- Quick Look modal -->
        <VibeModal v-model="quickOpen" fullscreen hide-footer>
            <template #header>
                <div class="d-flex align-items-center justify-content-between w-100">
                    <h5 class="modal-title text-truncate mb-0">
                        <VibeIcon :icon="quickFile ? iconFor(quickFile.type) : 'file-earmark'" class="me-2" />
                        {{ quickFile?.name }}
                    </h5>
                    <div class="d-flex gap-2 align-items-center ms-3">
                        <small class="text-muted">{{ quickIndex + 1 }} / {{ files.length }}</small>
                        <VibeButton variant="secondary" size="sm" outline title="Previous (←)" aria-label="Previous file" @click="quickStep(-1)">
                            <VibeIcon icon="chevron-left" />
                        </VibeButton>
                        <VibeButton variant="secondary" size="sm" outline title="Next (→)" aria-label="Next file" @click="quickStep(1)">
                            <VibeIcon icon="chevron-right" />
                        </VibeButton>
                        <VibeButton variant="success" size="sm" :href="`/download/${quickFile?.id}`">
                            <VibeIcon icon="download" class="me-1" />Download
                        </VibeButton>
                        <VibeDropdown
                            v-if="quickFile"
                            variant="primary"
                            size="sm"
                            :items="fileMenu(quickFile)"
                            @item-click="onQuickAction"
                        >
                            <VibeIcon icon="three-dots-vertical" class="me-1" />Actions
                        </VibeDropdown>
                        <VibeButton variant="secondary" size="sm" outline title="Close" aria-label="Close preview" @click="quickClose">
                            <VibeIcon icon="x-lg" />
                        </VibeButton>
                    </div>
                </div>
            </template>

            <div v-if="quickFile" class="quicklook-body d-flex flex-column align-items-center justify-content-center text-center" style="height: calc(100vh - 130px)">
                <img
                    v-if="imageMime(quickFile)"
                    :src="quickFile.url"
                    :alt="quickFile.name"
                    class="img-fluid rounded"
                    style="max-height: 100%; object-fit: contain"
                >
                <iframe
                    v-else-if="quickFile.type === 'pdf'"
                    :src="quickFile.url"
                    class="w-100 h-100 border rounded"
                ></iframe>
                <audio v-else-if="quickFile.mime?.startsWith('audio/')" :src="quickFile.url" controls class="w-100" />
                <video
                    v-else-if="quickFile.mime?.startsWith('video/')"
                    :src="quickFile.url"
                    controls
                    class="img-fluid rounded"
                    style="max-height: 100%"
                />
                <MarkdownViewer
                    v-else-if="previewMarkdownTypes.includes(quickFile.type)"
                    :key="quickFile.id"
                    :url="`/raw/${quickFile.id}`"
                    class="w-100 h-100 overflow-auto text-start"
                />
                <DocViewer
                    v-else-if="officeTypes.includes(quickFile.type)"
                    :key="quickFile.id"
                    :url="quickFile.url"
                    :type="quickFile.type"
                    class="w-100 h-100 overflow-auto text-start"
                />
                <pre
                    v-else-if="quickFile.metadata?.preview"
                    class="text-start p-3 bg-body-tertiary rounded border w-100 h-100"
                    style="overflow: auto; white-space: pre-wrap"
                >{{ quickFile.metadata.preview }}</pre>
                <div v-else class="text-muted py-5">
                    <VibeIcon :icon="iconFor(quickFile.type)" class="display-1 d-block mb-3" />
                    No inline preview for this file type.
                    <div class="small mt-2">{{ quickFile.mime || 'unknown type' }} · {{ (quickFile.size / 1024).toFixed(1) }} KB</div>
                </div>
            </div>
        </VibeModal>

        <!-- Upload modal -->
        <UploadModal v-model="uploadOpen" :parent-id="currentId" :max-upload-kb="maxUploadKb" :storage="storage" />

        <!-- Create folder modal -->
        <VibeModal v-model="folderOpen" title="Create Folder" centered>
            <form @submit.prevent="submitFolder">
                <VibeFormGroup
                    label="Folder Name"
                    :validation-state="folderForm.errors.folder_name ? 'invalid' : null"
                    :validation-message="folderForm.errors.folder_name"
                >
                    <VibeFormInput v-model="folderForm.folder_name" required />
                </VibeFormGroup>
            </form>
            <template #footer>
                <VibeButton variant="secondary" outline @click="folderOpen = false">Cancel</VibeButton>
                <VibeButton variant="primary" :disabled="folderForm.processing" @click="submitFolder"><VibeSpinner v-if="folderForm.processing" size="sm" class="me-1" />{{ folderForm.processing ? 'Creating…' : 'Create' }}</VibeButton>
            </template>
        </VibeModal>

        <!-- Rename modal -->
        <RenameModal v-model="renameOpen" :item="renameTarget" />

        <!-- Move / Copy modal -->
        <VibeModal v-model="transferOpen" :title="transferMode === 'move' ? 'Move To' : 'Copy To'" centered>
            <form @submit.prevent="submitTransfer">
                <VibeFormGroup
                    label="Destination Folder"
                    :validation-state="transferForm.errors.target_id ? 'invalid' : null"
                    :validation-message="transferForm.errors.target_id"
                >
                    <VibeFormSelect v-model="transferForm.target_id" :options="destinationOptions" />
                </VibeFormGroup>
            </form>
            <template #footer>
                <VibeButton variant="secondary" outline @click="transferOpen = false">Cancel</VibeButton>
                <VibeButton variant="primary" :disabled="transferForm.processing" @click="submitTransfer">
                    <VibeSpinner v-if="transferForm.processing" size="sm" class="me-1" />{{ transferForm.processing ? (transferMode === 'move' ? 'Moving…' : 'Copying…') : (transferMode === 'move' ? 'Move' : 'Copy') }}
                </VibeButton>
            </template>
        </VibeModal>

        <!-- New Folder from Selection -->
        <VibeModal v-model="batchFolderOpen" title="New Folder from Selection" centered>
            <div class="mx-auto" style="max-width: 520px">
                <VibeFormGroup label="Folder name">
                    <VibeFormInput v-model="batchFolderName" />
                </VibeFormGroup>
                <p class="text-muted small mt-2">{{ selectedIds.size }} item(s) will be moved into it.</p>
            </div>
            <template #footer>
                <VibeButton variant="secondary" outline @click="batchFolderOpen = false">Cancel</VibeButton>
                <VibeButton variant="primary" :disabled="!batchFolderName || batchBusy" @click="submitBatchFolder">Create</VibeButton>
            </template>
        </VibeModal>

        <!-- Batch move -->
        <VibeModal v-model="batchMoveOpen" title="Move Selection To" centered>
            <div class="mx-auto" style="max-width: 520px">
                <VibeFormGroup label="Destination folder">
                    <VibeFormSelect v-model="batchMoveTarget" :options="destinationOptions" />
                </VibeFormGroup>
            </div>
            <template #footer>
                <VibeButton variant="secondary" outline @click="batchMoveOpen = false">Cancel</VibeButton>
                <VibeButton variant="primary" :disabled="batchBusy" @click="submitBatchMove">Move {{ selectedIds.size }}</VibeButton>
            </template>
        </VibeModal>

        <!-- Batch rename -->
        <VibeModal v-model="batchRenameOpen" title="Batch Rename" size="lg" centered scrollable>
            <div class="mx-auto" style="max-width: 760px">
                <VibeButtonGroup class="mb-3">
                    <VibeButton :variant="renameOpts.mode === 'replace' ? 'primary' : 'secondary'" :outline="renameOpts.mode !== 'replace'" @click="renameOpts.mode = 'replace'">Find &amp; Replace</VibeButton>
                    <VibeButton :variant="renameOpts.mode === 'add' ? 'primary' : 'secondary'" :outline="renameOpts.mode !== 'add'" @click="renameOpts.mode = 'add'">Add Text</VibeButton>
                    <VibeButton :variant="renameOpts.mode === 'number' ? 'primary' : 'secondary'" :outline="renameOpts.mode !== 'number'" @click="renameOpts.mode = 'number'">Numbering</VibeButton>
                </VibeButtonGroup>

                <div v-if="renameOpts.mode === 'replace'" class="row g-2">
                    <div class="col"><VibeFormGroup label="Find"><VibeFormInput v-model="renameOpts.find" /></VibeFormGroup></div>
                    <div class="col"><VibeFormGroup label="Replace with"><VibeFormInput v-model="renameOpts.replace" /></VibeFormGroup></div>
                </div>
                <div v-else-if="renameOpts.mode === 'add'" class="row g-2 align-items-end">
                    <div class="col"><VibeFormGroup label="Text"><VibeFormInput v-model="renameOpts.text" /></VibeFormGroup></div>
                    <div class="col-auto">
                        <VibeFormGroup label="Position">
                            <VibeFormSelect v-model="renameOpts.position" :options="[{ value: 'before', text: 'Before name' }, { value: 'after', text: 'After name' }]" />
                        </VibeFormGroup>
                    </div>
                </div>
                <div v-else class="row g-2 align-items-end">
                    <div class="col"><VibeFormGroup label="Base name"><VibeFormInput v-model="renameOpts.base" placeholder="(keep original)" /></VibeFormGroup></div>
                    <div class="col-auto"><VibeFormGroup label="Start at"><VibeFormInput v-model.number="renameOpts.start" type="number" style="width: 90px" /></VibeFormGroup></div>
                    <div class="col-auto"><VibeFormGroup label="Digits"><VibeFormInput v-model.number="renameOpts.pad" type="number" style="width: 80px" /></VibeFormGroup></div>
                </div>

                <h6 class="mt-3 text-muted">Preview</h6>
                <div class="border rounded" style="max-height: 50vh; overflow: auto">
                    <table class="table table-sm mb-0">
                        <tbody>
                            <tr v-for="r in renamePreview" :key="r.id">
                                <td class="text-muted text-truncate" style="max-width: 280px">{{ r.from }}</td>
                                <td class="text-center text-muted"><VibeIcon icon="arrow-right" /></td>
                                <td class="fw-medium text-truncate" :class="{ 'text-danger': r.to !== r.from && renamePreview.filter(x => x.to === r.to).length > 1 }">{{ r.to }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
            <template #footer>
                <VibeButton variant="secondary" outline @click="batchRenameOpen = false">Cancel</VibeButton>
                <VibeButton variant="primary" :disabled="batchBusy" @click="submitBatchRename">Rename {{ selectedIds.size }}</VibeButton>
            </template>
        </VibeModal>

        <!-- Advanced search -->
        <AdvancedSearchModal v-model="advOpen" :filters="filters" :all-tags="allTags" />
    </AppLayout>
</template>

<style scoped>
.select-check {
    cursor: pointer;
}
/* Batch action bar sticks to the top while scrolling a long selection. */
.batch-bar {
    position: sticky;
    top: 0.5rem;
    z-index: 5;
}
/* Brief confirmation pulse when a tag is added. */
.tag-flash {
    animation: tag-flash 0.6s ease-out;
}
@keyframes tag-flash {
    0% { box-shadow: 0 0 0 0 rgba(13, 110, 253, 0.6); transform: scale(1.12); }
    100% { box-shadow: 0 0 0 8px rgba(13, 110, 253, 0); transform: scale(1); }
}
</style>
