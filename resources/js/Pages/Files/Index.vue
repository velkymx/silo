<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import FolderTree from '../../Components/FolderTree.vue';
import MarkdownEditor from '../../Components/MarkdownEditor.vue';
import MarkdownViewer from '../../Components/MarkdownViewer.vue';
import DocViewer from '../../Components/DocViewer.vue';

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

const maxUploadBytes = computed(() => props.maxUploadKb * 1024);
const maxUploadLabel = computed(() =>
    props.maxUploadKb >= 1024 ? `${(props.maxUploadKb / 1024).toFixed(0)} MB` : `${props.maxUploadKb} KB`
);

function fmtBytes(n) {
    if (n < 1024) return `${n} B`;
    const units = ['KB', 'MB', 'GB', 'TB'];
    let v = n / 1024;
    let i = 0;
    while (v >= 1024 && i < units.length - 1) { v /= 1024; i++; }
    return `${v.toFixed(1)} ${units[i]}`;
}

const storagePct = computed(() =>
    props.storage.quota > 0 ? Math.min(100, Math.round((props.storage.used / props.storage.quota) * 100)) : 0
);
const storageBars = computed(() => [{
    value: storagePct.value,
    variant: storagePct.value > 90 ? 'danger' : storagePct.value > 75 ? 'warning' : 'success',
}]);

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
    { text: 'My Files', folder: null, active: !props.current },
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

const imageTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
const videoTypes = ['mp4', 'mov', 'webm', 'mkv', 'avi', 'm4v'];
const audioTypes = ['mp3', 'wav', 'ogg', 'flac', 'm4a', 'aac'];

function typeLabel(item) {
    if (item.is_dir) return 'Folder';
    const t = item.type;
    if (imageTypes.includes(t)) return 'Image';
    if (t === 'pdf') return 'PDF';
    if (videoTypes.includes(t)) return 'Video';
    if (audioTypes.includes(t)) return 'Audio';
    if (['zip', 'rar', '7z', 'tar', 'gz'].includes(t)) return 'Archive';
    if (['doc', 'docx', 'rtf', 'txt', 'md'].includes(t)) return 'Document';
    if (['xls', 'xlsx', 'csv'].includes(t)) return 'Spreadsheet';
    return t ? t.toUpperCase() : 'File';
}

function colorFor(item) {
    if (item.is_dir) return '#f59e0b';
    const t = item.type;
    if (imageTypes.includes(t)) return '#10b981';
    if (t === 'pdf') return '#ef4444';
    if (videoTypes.includes(t)) return '#6366f1';
    if (audioTypes.includes(t)) return '#ec4899';
    if (['zip', 'rar', '7z', 'tar', 'gz'].includes(t)) return '#f59e0b';
    if (['xls', 'xlsx', 'csv'].includes(t)) return '#22c55e';
    if (['doc', 'docx', 'rtf', 'txt', 'md'].includes(t)) return '#3b82f6';
    return '#6b7280';
}

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
const editContent = ref('');
const editLoading = ref(false);
const editSaving = ref(false);
const editCreating = ref(false);
const editName = ref('');

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
    editContent.value = '';
    editLoading.value = true;
    editOpen.value = true;
    try {
        const { data } = await window.axios.get(`/raw/${item.id}`, { responseType: 'text', transformResponse: [(d) => d] });
        editContent.value = typeof data === 'string' ? data : String(data ?? '');
    } finally {
        editLoading.value = false;
    }
}

function openNewMarkdown() {
    editItem.value = null;
    editCreating.value = true;
    editKind.value = 'markdown';
    editName.value = 'untitled.md';
    editContent.value = '';
    editLoading.value = false;
    editOpen.value = true;
}

function saveEdit() {
    editSaving.value = true;
    const done = {
        preserveScroll: true,
        onSuccess: () => { editOpen.value = false; },
        onFinish: () => { editSaving.value = false; },
    };
    if (editCreating.value) {
        router.post('/files/text', { name: editName.value, content: editContent.value, parent_id: currentId.value }, done);
    } else {
        router.put(`/files/${editItem.value.id}/content`, { content: editContent.value }, done);
    }
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

function openItem(item) {
    if (item.is_dir) {
        visitFolder(item.id);
    } else {
        quickLook(item);
    }
}

function selectFile(item) {
    if (item.is_dir) return;
    const idx = props.files.findIndex((f) => f.id === item.id);
    if (idx >= 0) selectedIndex.value = idx;
}

// List vs thumbnail grid view, remembered across visits.
const viewMode = ref(localStorage.getItem('fm-view') === 'grid' ? 'grid' : 'list');
watch(viewMode, (v) => localStorage.setItem('fm-view', v));

function iconFor(type) {
    if (imageTypes.includes(type)) return 'file-earmark-image';
    if (type === 'pdf') return 'file-earmark-pdf';
    if (['zip', 'rar', '7z', 'tar', 'gz'].includes(type)) return 'file-earmark-zip';
    if (['doc', 'docx', 'txt', 'md'].includes(type)) return 'file-earmark-text';
    return 'file-earmark';
}

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
    if (action.action === 'download') window.location.href = `/download/${item.id}`;
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

function restoreVersion(version) {
    if (!confirm(`Restore version ${version.version}? Current content is kept in history.`)) return;
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
const quickOpen = ref(false);
const quickIndex = ref(0);
const quickFile = computed(() => props.files[quickIndex.value] ?? null);

function quickLook(file) {
    const idx = props.files.findIndex((f) => f.id === file.id);
    quickIndex.value = idx >= 0 ? idx : 0;
    quickOpen.value = true;
}

function quickStep(delta) {
    if (!props.files.length) return;
    quickIndex.value = (quickIndex.value + delta + props.files.length) % props.files.length;
}

// Spacebar opens Quick Look for the selected row; arrows page through files.
const selectedIndex = ref(0);

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
        if (e.key === 'Escape') quickOpen.value = false;
        if (e.key === ' ') { e.preventDefault(); quickOpen.value = false; }
        return;
    }

    if (e.key === ' ' && props.files.length) {
        e.preventDefault();
        quickIndex.value = Math.min(selectedIndex.value, props.files.length - 1);
        quickOpen.value = true;
    }
}

const imageMime = (f) => f && (f.type ? imageTypes.includes(f.type) : false);

// ----- Upload -----
const uploadOpen = ref(false);
const uploadFiles = ref([]);
const uploadForm = useForm({ files: [], parent_id: currentId.value });

watch(uploadFiles, (val) => {
    uploadForm.files = val;
});

function submitUpload() {
    uploadForm.parent_id = currentId.value;
    uploadForm.post('/upload', {
        forceFormData: true,
        onSuccess: () => {
            uploadForm.reset();
            uploadFiles.value = [];
            uploadOpen.value = false;
        },
    });
}

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
const renameItem = ref(null);
const renameForm = useForm({ name: '' });

function openRename(item) {
    renameItem.value = item;
    renameForm.clearErrors();
    renameForm.name = item.name;
    renameOpen.value = true;
}

function submitRename() {
    renameForm.patch(`/files/${renameItem.value.id}/rename`, {
        preserveScroll: true,
        onSuccess: () => {
            renameOpen.value = false;
        },
    });
}

// ----- Move / Copy -----
const transferOpen = ref(false);
const transferMode = ref('move');
const transferItem = ref(null);
const transferForm = useForm({ target_id: null });

// Folders the item cannot be moved/copied into (itself + its descendants).
function descendantIds(folderId) {
    const ids = new Set([folderId]);
    let added = true;
    while (added) {
        added = false;
        for (const f of props.allFolders) {
            if (f.parent_id && ids.has(f.parent_id) && !ids.has(f.id)) {
                ids.add(f.id);
                added = true;
            }
        }
    }
    return ids;
}

const destinationOptions = computed(() => {
    const item = transferItem.value;
    const excluded = item?.is_dir ? descendantIds(item.id) : new Set();
    const options = [{ value: null, text: 'Home (root)' }];
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
const shareItem = ref(null);
const shareGrants = ref([]);
const shareInherited = ref([]);
const shareGroups = ref([]);
const shareError = ref('');
const shareBusy = ref(false);
const grant = ref({ subject_type: 'user', email: '', group_id: null, abilities: ['read'] });
const abilityOptions = ['read', 'write', 'delete', 'share'];

const subjectTypeOptions = [
    { value: 'user', text: 'User (email)' },
    { value: 'group', text: 'Group' },
];

function resetGrant() {
    grant.value = { subject_type: 'user', email: '', group_id: null, abilities: ['read'] };
    shareError.value = '';
}

const shareLinks = ref([]);
const linkForm = ref({ allow_download: true, expires_in_days: null, password: '' });
const linkCopied = ref(null);

async function openShare(item) {
    shareItem.value = item;
    resetGrant();
    shareGrants.value = [];
    shareLinks.value = [];
    linkForm.value = { allow_download: true, expires_in_days: null, password: '' };
    shareOpen.value = true;
    const [{ data: perms }, { data: links }] = await Promise.all([
        window.axios.get(`/files/${item.id}/permissions`),
        window.axios.get(`/files/${item.id}/links`),
    ]);
    shareGrants.value = perms.permissions;
    shareInherited.value = perms.inherited ?? [];
    shareGroups.value = perms.groups.map((g) => ({ value: g.id, text: g.name }));
    shareLinks.value = links.links;
}

async function createLink() {
    const payload = {
        allow_download: linkForm.value.allow_download,
        expires_in_days: linkForm.value.expires_in_days || null,
        password: linkForm.value.password || null,
    };
    const { data } = await window.axios.post(`/files/${shareItem.value.id}/links`, payload);
    shareLinks.value = data.links;
    linkForm.value = { allow_download: true, expires_in_days: null, password: '' };
}

async function revokeLink(id) {
    const { data } = await window.axios.delete(`/files/${shareItem.value.id}/links/${id}`);
    shareLinks.value = data.links;
}

function copyLink(url, id) {
    navigator.clipboard?.writeText(url);
    linkCopied.value = id;
    setTimeout(() => (linkCopied.value = null), 1500);
}

function toggleAbility(ability) {
    const set = new Set(grant.value.abilities);
    set.has(ability) ? set.delete(ability) : set.add(ability);
    grant.value.abilities = [...set];
}

async function addGrant() {
    shareError.value = '';
    shareBusy.value = true;
    try {
        const { data } = await window.axios.post(`/files/${shareItem.value.id}/permissions`, grant.value);
        shareGrants.value = data.permissions;
        resetGrant();
    } catch (e) {
        const errs = e.response?.data?.errors;
        shareError.value = errs ? Object.values(errs).flat().join(' ') : 'Could not add grant.';
    } finally {
        shareBusy.value = false;
    }
}

async function removeGrant(id) {
    const { data } = await window.axios.delete(`/files/${shareItem.value.id}/permissions/${id}`);
    shareGrants.value = data.permissions;
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

function addTag(name) {
    const value = (name ?? tagInput.value).trim();
    if (value && !tagList.value.includes(value)) tagList.value.push(value);
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
function destroy(item) {
    if (!confirm(`Delete "${item.name}"? This cannot be undone.`)) return;
    router.delete(`/delete/${item.id}`, { preserveScroll: true });
}

// ----- Background-job status polling -----
const hasPending = computed(() => props.files.some((f) => f.status === 'pending'));
let poll = null;

function startPolling() {
    if (poll || !hasPending.value) return;
    poll = setInterval(() => {
        if (!hasPending.value) {
            clearInterval(poll);
            poll = null;
            return;
        }
        router.reload({ only: ['files'], preserveScroll: true });
    }, 3000);
}

watch(hasPending, (pending) => {
    if (pending) startPolling();
});

onMounted(() => {
    startPolling();
    window.addEventListener('keydown', onKey);
});
onBeforeUnmount(() => {
    poll && clearInterval(poll);
    window.removeEventListener('keydown', onKey);
});
</script>

<template>
    <AppLayout>
        <template #topbar>
            <VibeInputGroup class="mx-auto" style="max-width: 560px">
                <template #prepend>
                    <span class="input-group-text bg-body border-end-0"><VibeIcon icon="search" class="text-muted" /></span>
                </template>
                <VibeFormInput
                    id="global-search"
                    v-model="search"
                    type="search"
                    class="border-start-0"
                    placeholder="Search files, folders, tags…"
                    no-wrapper
                    @keyup.enter="runSearch"
                />
                <template #append>
                    <VibeButton v-if="searching" variant="secondary" outline @click="clearSearch">
                        <VibeIcon icon="x-lg" />
                    </VibeButton>
                    <span v-else class="input-group-text bg-body text-muted">
                        <kbd class="bg-body-secondary text-body-secondary border" style="font-size: 0.7rem">⌘K</kbd>
                    </span>
                </template>
            </VibeInputGroup>
        </template>

        <template #sidebar>
            <div class="text-muted text-uppercase small fw-semibold mb-2 px-1">Folders</div>
            <VibeButton
                variant="link"
                class="p-0 text-decoration-none mb-1 d-block px-1"
                :class="!current ? 'fw-bold' : 'text-body'"
                @click="visitFolder(null)"
            >
                <VibeIcon icon="house-door-fill" class="me-1" />Home
            </VibeButton>
            <FolderTree :folders="allFolders" :current-id="currentId" :open-ids="ancestorIds" />

            <template v-if="allTags.length">
                <div class="text-muted text-uppercase small fw-semibold mt-4 mb-2 px-1">Tags</div>
                <div class="d-flex flex-wrap gap-1 px-1">
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

        <template #sidebar-bottom>
            <div class="text-muted text-uppercase small fw-semibold mb-2 px-1">Storage</div>
            <div class="px-1">
                <template v-if="storage.quota > 0">
                    <VibeProgress :bars="storageBars" class="mb-1" />
                    <div class="small text-muted">
                        {{ fmtBytes(storage.used) }} of {{ fmtBytes(storage.quota) }} ({{ storagePct }}%)
                    </div>
                </template>
                <div v-else class="small text-muted">{{ fmtBytes(storage.used) }} used · unlimited</div>
                <a href="/profile" class="small text-decoration-none d-inline-block mt-1" @click.prevent="router.visit('/profile')">Manage storage</a>
            </div>
        </template>

        <div class="d-flex justify-content-between align-items-center mb-3 gap-2">
            <VibeBreadcrumb :items="breadcrumbItems" class="mb-0 text-truncate" @item-click="onBreadcrumb" />
            <div class="d-flex align-items-center gap-2 flex-shrink-0">
                <VibeButton variant="primary" @click="uploadOpen = true">
                    <VibeIcon icon="upload" class="me-1" />Upload
                </VibeButton>
                <VibeDropdown variant="secondary" outline :items="newMenu" @item-click="onNewMenu">
                    <template #button><VibeIcon icon="plus-lg" class="me-1" />New</template>
                    <template #item="{ item }"><VibeIcon :icon="item.icon" class="me-2" />{{ item.text }}</template>
                </VibeDropdown>
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

        <VibeAlert v-if="searching" variant="info" class="d-flex align-items-center justify-content-between">
            <span><VibeIcon icon="search" class="me-1" />Results for "{{ filters.search }}" across all folders.</span>
            <VibeButton variant="info" size="sm" outline @click="clearSearch">Clear</VibeButton>
        </VibeAlert>

        <VibeAlert v-if="activeTag" variant="primary" class="d-flex align-items-center justify-content-between">
            <span><VibeIcon icon="tag-fill" class="me-1" />Files tagged "{{ activeTag.name }}".</span>
            <VibeButton variant="primary" size="sm" outline @click="filterByTag(null)">Clear</VibeButton>
        </VibeAlert>

        <VibeAlert v-if="starredOnly" variant="warning" class="d-flex align-items-center justify-content-between">
            <span><VibeIcon icon="star-fill" class="me-1" />Starred items.</span>
            <VibeButton variant="warning" size="sm" outline @click="router.get('/', {}, { preserveScroll: true })">Clear</VibeButton>
        </VibeAlert>

        <VibeAlert v-if="recentOnly" variant="secondary" class="d-flex align-items-center justify-content-between">
            <span><VibeIcon icon="clock-history" class="me-1" />Recent uploads.</span>
            <VibeButton variant="secondary" size="sm" outline @click="router.get('/', {}, { preserveScroll: true })">Clear</VibeButton>
        </VibeAlert>

        <!-- Thumbnail / grid view -->
        <VibeRow v-if="viewMode === 'grid'" class="g-3">
            <VibeCol v-for="item in items" :key="item.id" :xs="6" :sm="4" :md="3" :xl="2">
              <VibeDraggable :payload="item" group="files" tag="div" class="h-100">
               <template #default="{ isDragging }">
                <component
                    :is="item.is_dir ? 'VibeDroppable' : 'div'"
                    group="files"
                    class="h-100"
                    @drop="onDropToFolder($event, item)"
                >
                 <template #default="drop">
                <div
                    class="card h-100 text-center border position-relative"
                    :class="{ 'opacity-50': isDragging, 'border-primary border-2 shadow': drop && drop.isOver }"
                    style="cursor: pointer"
                    @click="openItem(item)"
                >
                    <VibeButton
                        variant="link"
                        class="position-absolute top-0 start-0 m-1 p-1"
                        :title="item.starred ? 'Unstar' : 'Star'"
                        @click.stop="toggleStar(item)"
                    >
                        <VibeIcon :icon="item.starred ? 'star-fill' : 'star'" :class="item.starred ? 'text-warning' : 'text-muted'" />
                    </VibeButton>
                    <div class="position-absolute top-0 end-0 m-1" @click.stop>
                        <VibeDropdown
                            size="sm"
                            variant="light"
                            menu-end
                            :items="item.is_dir ? folderActions : fileMenu(item)"
                            @item-click="onAction(item, $event)"
                        >
                            <template #button><VibeIcon icon="three-dots-vertical" /></template>
                            <template #item="{ item: a }"><VibeIcon :icon="a.icon" class="me-2" />{{ a.text }}</template>
                        </VibeDropdown>
                    </div>
                    <div class="d-flex align-items-center justify-content-center bg-body-tertiary rounded-top" style="height: 120px">
                        <img
                            v-if="item.thumb_url"
                            :src="item.thumb_url"
                            :alt="item.name"
                            class="w-100 h-100"
                            style="object-fit: cover"
                        >
                        <VibeIcon
                            v-else
                            :icon="item.is_dir ? 'folder-fill' : iconFor(item.type)"
                            class="display-4"
                            :style="{ color: colorFor(item) }"
                        />
                    </div>
                    <div class="p-2">
                        <div class="text-truncate small fw-medium" :title="item.name">{{ item.name }}</div>
                        <div class="text-muted" style="font-size: 0.7rem">
                            {{ item.is_dir ? `${item.item_count} items` : `${(item.size / 1024).toFixed(1)} KB` }}
                        </div>
                        <VibeBadge v-if="item.status === 'pending'" variant="info" class="mt-1">Processing</VibeBadge>
                        <VibeBadge v-else-if="item.status === 'infected'" variant="danger" class="mt-1">
                            <VibeIcon icon="shield-exclamation" class="me-1" />Infected
                        </VibeBadge>
                        <VibeBadge v-else-if="item.status === 'failed'" variant="danger" class="mt-1">Failed</VibeBadge>
                    </div>
                </div>
                 </template>
                </component>
               </template>
              </VibeDraggable>
            </VibeCol>
            <VibeCol v-if="!items.length" :cols="12">
                <p class="text-muted text-center py-4">{{ flat ? 'No matching files.' : 'This folder is empty.' }}</p>
            </VibeCol>
        </VibeRow>

        <VibeDataTable
            v-else
            :items="items"
            :columns="columns"
            row-key="id"
            hover
            :searchable="false"
            :per-page="10"
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
              <VibeDraggable :payload="item" group="files" tag="div">
               <template #default="{ isDragging }">
                <component
                    :is="item.is_dir ? 'VibeDroppable' : 'div'"
                    group="files"
                    @drop="onDropToFolder($event, item)"
                >
                 <template #default="drop">
                <div
                    class="d-flex align-items-center rounded"
                    :class="{ 'opacity-50': isDragging, 'bg-primary-subtle': drop && drop.isOver }"
                    style="cursor: pointer"
                    @click="openItem(item)"
                >
                    <img
                        v-if="item.thumb_url"
                        :src="item.thumb_url"
                        :alt="item.name"
                        class="rounded border me-2 flex-shrink-0"
                        style="width: 36px; height: 36px; object-fit: cover"
                    >
                    <VibeIcon
                        v-else
                        :icon="item.is_dir ? 'folder-fill' : iconFor(item.type)"
                        class="me-2 fs-4 flex-shrink-0"
                        :style="{ color: colorFor(item) }"
                    />
                    <span class="text-truncate">{{ item.name }}</span>
                    <VibeBadge v-if="item.status === 'pending'" variant="info" class="ms-2">
                        <VibeSpinner size="sm" class="me-1" />Processing
                    </VibeBadge>
                    <VibeBadge v-else-if="item.status === 'infected'" variant="danger" class="ms-2">
                        <VibeIcon icon="shield-exclamation" class="me-1" />Infected
                    </VibeBadge>
                    <VibeBadge v-else-if="item.status === 'failed'" variant="danger" class="ms-2">Failed</VibeBadge>
                    <VibeBadge v-if="item.version > 1" variant="secondary" class="ms-2">v{{ item.version }}</VibeBadge>
                </div>
                <div v-if="item.tags?.length" class="mt-1" style="padding-left: 2.75rem">
                    <span
                        v-for="tag in item.tags"
                        :key="tag.id"
                        class="badge rounded-pill me-1"
                        :style="{ backgroundColor: tag.color || '#6c757d', cursor: 'pointer' }"
                        @click.stop="filterByTag(tag.id)"
                    >{{ tag.name }}</span>
                </div>
                 </template>
                </component>
               </template>
              </VibeDraggable>
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
                <div class="d-flex justify-content-end gap-1 align-items-center" @click.stop>
                    <VibeButton
                        variant="link"
                        class="p-0 me-1"
                        :title="item.starred ? 'Unstar' : 'Star'"
                        @click="toggleStar(item)"
                    >
                        <VibeIcon :icon="item.starred ? 'star-fill' : 'star'" :class="item.starred ? 'text-warning' : 'text-muted'" />
                    </VibeButton>
                    <VibeDropdown
                        size="sm"
                        variant="light"
                        menu-end
                        :items="item.is_dir ? folderActions : fileMenu(item)"
                        @item-click="onAction(item, $event)"
                    >
                        <template #button><VibeIcon icon="three-dots-vertical" /></template>
                        <template #item="{ item: a }">
                            <VibeIcon :icon="a.icon" class="me-2" />{{ a.text }}
                        </template>
                    </VibeDropdown>
                </div>
            </template>
        </VibeDataTable>

        <!-- Editor modal -->
        <VibeModal
            v-model="editOpen"
            :title="editCreating ? 'New Markdown file' : `Edit — ${editItem?.name || ''}`"
            fullscreen
        >
            <div v-if="editLoading" class="text-center py-5 text-muted">
                <VibeSpinner class="me-2" />Loading…
            </div>
            <template v-else>
                <VibeFormGroup v-if="editCreating" label="File name" class="mb-3">
                    <VibeFormInput v-model="editName" placeholder="untitled.md" />
                </VibeFormGroup>
                <MarkdownEditor v-if="editKind === 'markdown'" v-model="editContent" />
                <VibeFormWysiwyg v-else v-model="editContent" height="60vh" />
            </template>
            <template #footer>
                <VibeButton variant="secondary" outline @click="editOpen = false">Cancel</VibeButton>
                <VibeButton variant="primary" :disabled="editSaving || editLoading" @click="saveEdit">
                    <VibeIcon icon="save" class="me-1" />{{ editCreating ? 'Create' : 'Save' }}
                </VibeButton>
            </template>
        </VibeModal>

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
        <VibeModal v-model="shareOpen" :title="`Share — ${shareItem?.name || ''}`" fullscreen hide-footer>
            <h6 class="text-muted">People &amp; groups with access</h6>
            <table v-if="shareGrants.length" class="table table-sm align-middle">
                <tbody>
                    <tr v-for="g in shareGrants" :key="g.id">
                        <td>
                            <VibeIcon :icon="g.subject_type === 'group' ? 'people' : 'person'" class="me-1" />
                            {{ g.subject_label }}
                        </td>
                        <td><VibeBadge variant="secondary">{{ g.ability }}</VibeBadge></td>
                        <td class="text-end">
                            <VibeButton variant="danger" size="sm" outline @click="removeGrant(g.id)">
                                <VibeIcon icon="x" />
                            </VibeButton>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-else class="text-muted small">No direct grants on this item.</p>

            <template v-if="shareInherited.length">
                <h6 class="text-muted">Inherited from parent folders</h6>
                <table class="table table-sm align-middle">
                    <tbody>
                        <tr v-for="(g, i) in shareInherited" :key="i" class="text-muted">
                            <td>
                                <VibeIcon :icon="g.subject_type === 'group' ? 'people' : 'person'" class="me-1" />
                                {{ g.subject_label }}
                            </td>
                            <td><VibeBadge variant="light" class="text-dark border">{{ g.ability }}</VibeBadge></td>
                            <td class="small"><VibeIcon icon="folder" class="me-1" />{{ g.source }}</td>
                        </tr>
                    </tbody>
                </table>
            </template>

            <hr>
            <h6 class="text-muted">Grant access</h6>
            <VibeAlert v-if="shareError" variant="danger">{{ shareError }}</VibeAlert>
            <div class="row g-2">
                <div class="col-5">
                    <VibeFormSelect v-model="grant.subject_type" :options="subjectTypeOptions" />
                </div>
                <div class="col-7">
                    <VibeFormInput
                        v-if="grant.subject_type === 'user'"
                        v-model="grant.email"
                        type="email"
                        placeholder="person@example.com"
                    />
                    <VibeFormSelect
                        v-else
                        v-model="grant.group_id"
                        :options="shareGroups"
                        placeholder="Choose a group…"
                    />
                </div>
            </div>
            <div class="d-flex flex-wrap gap-3 mt-3">
                <VibeFormCheckbox
                    v-for="a in abilityOptions"
                    :key="a"
                    :model-value="grant.abilities.includes(a)"
                    :label="a"
                    @update:model-value="toggleAbility(a)"
                />
            </div>
            <div class="text-end mt-3">
                <VibeButton variant="primary" :disabled="shareBusy" @click="addGrant">Grant</VibeButton>
            </div>

            <template v-if="!shareItem?.is_dir">
            <hr>
            <h6 class="text-muted">Public links</h6>
            <table v-if="shareLinks.length" class="table table-sm align-middle">
                <tbody>
                    <tr v-for="link in shareLinks" :key="link.id">
                        <td class="text-truncate" style="max-width: 220px">
                            <a :href="link.url" target="_blank" class="small">{{ link.url }}</a>
                            <div class="small text-muted">
                                <span v-if="link.protected"><VibeIcon icon="lock" /> password · </span>
                                <span>{{ link.allow_download ? 'download' : 'view only' }}</span>
                                <span v-if="link.expires_at"> · expires {{ link.expires_at }}</span>
                                <span v-if="link.expired" class="text-danger"> · expired</span>
                            </div>
                        </td>
                        <td class="text-end text-nowrap">
                            <VibeButton variant="secondary" size="sm" outline @click="copyLink(link.url, link.id)">
                                <VibeIcon :icon="linkCopied === link.id ? 'check' : 'clipboard'" />
                            </VibeButton>
                            <VibeButton variant="danger" size="sm" outline class="ms-1" @click="revokeLink(link.id)">
                                <VibeIcon icon="x" />
                            </VibeButton>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p v-else class="text-muted small">No public links.</p>

            <div class="row g-2 align-items-center">
                <div class="col-auto">
                    <VibeFormCheckbox v-model="linkForm.allow_download" label="Allow download" />
                </div>
                <div class="col">
                    <VibeFormInput
                        v-model="linkForm.expires_in_days"
                        type="number"
                        placeholder="Expires in N days (optional)"
                    />
                </div>
                <div class="col">
                    <VibeFormInput
                        v-model="linkForm.password"
                        type="password"
                        placeholder="Password (optional)"
                    />
                </div>
                <div class="col-auto">
                    <VibeButton variant="primary" @click="createLink">Create link</VibeButton>
                </div>
            </div>
            </template>
        </VibeModal>

        <!-- Tags modal -->
        <VibeModal v-model="tagsOpen" :title="`Tags — ${tagsItem?.name || ''}`" fullscreen>
            <div class="d-flex flex-wrap gap-2 mb-3">
                <VibeBadge v-for="name in tagList" :key="name" variant="primary" class="d-flex align-items-center">
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
                <VibeButton variant="primary" :disabled="tagSaving" @click="saveTags">Save</VibeButton>
            </template>
        </VibeModal>

        <!-- Versions modal -->
        <VibeModal v-model="versionsOpen" :title="`Versions — ${versionsItem?.name || ''}`" centered fullscreen hide-footer>
            <p class="text-muted small">
                Current: version {{ versionsItem?.version }}.
                <span v-if="!versionsItem?.versions?.length">No previous versions.</span>
            </p>
            <table v-if="versionsItem?.versions?.length" class="table table-sm align-middle mb-0">
                <thead>
                    <tr><th>Version</th><th>What changed</th><th>Size</th><th>Saved</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    <tr v-for="v in versionsItem.versions" :key="v.id">
                        <td>v{{ v.version }}</td>
                        <td class="small">
                            <span v-if="v.note">{{ v.note }}</span>
                            <span v-else class="text-muted fst-italic">—</span>
                        </td>
                        <td>{{ (v.size / 1024).toFixed(2) }} KB</td>
                        <td class="small">{{ v.created_at }}</td>
                        <td class="text-end">
                            <VibeButton
                                variant="success"
                                size="sm"
                                :href="`/files/${versionsItem.id}/versions/${v.id}/download`"
                            >
                                <VibeIcon icon="download" />
                            </VibeButton>
                            <VibeButton variant="primary" size="sm" outline class="ms-1" @click="restoreVersion(v)">
                                <VibeIcon icon="arrow-counterclockwise" class="me-1" />Restore
                            </VibeButton>
                        </td>
                    </tr>
                </tbody>
            </table>
        </VibeModal>

        <!-- Quick Look modal -->
        <VibeModal v-model="quickOpen" size="xl" centered fullscreen hide-footer>
            <template #header>
                <div class="d-flex align-items-center justify-content-between w-100">
                    <h5 class="modal-title text-truncate mb-0">
                        <VibeIcon :icon="quickFile ? iconFor(quickFile.type) : 'file-earmark'" class="me-2" />
                        {{ quickFile?.name }}
                    </h5>
                    <div class="d-flex gap-2 align-items-center ms-3">
                        <small class="text-muted">{{ quickIndex + 1 }} / {{ files.length }}</small>
                        <VibeButton variant="secondary" size="sm" outline title="Previous (←)" @click="quickStep(-1)">
                            <VibeIcon icon="chevron-left" />
                        </VibeButton>
                        <VibeButton variant="secondary" size="sm" outline title="Next (→)" @click="quickStep(1)">
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
        <VibeModal v-model="uploadOpen" title="Upload Files" fullscreen>
            <div class="mx-auto" style="max-width: 640px">
                <form @submit.prevent="submitUpload">
                    <VibeFileInput
                        v-model="uploadFiles"
                        label="Choose Files"
                        multiple
                        drag-drop
                        :max-size="maxUploadBytes"
                        :help-text="`Up to ${maxUploadLabel} per file.`"
                    />
                    <p v-if="uploadForm.errors['files.0']" class="text-danger small mt-2">
                        {{ uploadForm.errors['files.0'] }}
                    </p>
                    <p v-if="uploadFiles.length" class="text-muted small mt-2 mb-0">
                        {{ uploadFiles.length }} file{{ uploadFiles.length === 1 ? '' : 's' }} ready to upload.
                    </p>
                    <VibeProgress
                        v-if="uploadForm.progress"
                        :bars="[{ value: uploadForm.progress.percentage, showValue: true, variant: 'success' }]"
                        class="mt-3"
                    />
                </form>
            </div>
            <template #footer>
                <div class="d-flex justify-content-center gap-2 w-100">
                    <VibeButton variant="secondary" outline @click="uploadOpen = false">Cancel</VibeButton>
                    <VibeButton
                        variant="primary"
                        :disabled="uploadForm.processing || !uploadFiles.length"
                        @click="submitUpload"
                    >
                        <VibeIcon icon="upload" class="me-1" />Upload{{ uploadFiles.length ? ` ${uploadFiles.length}` : '' }}
                    </VibeButton>
                </div>
            </template>
        </VibeModal>

        <!-- Create folder modal -->
        <VibeModal v-model="folderOpen" title="Create Folder" fullscreen>
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
                <VibeButton variant="primary" :disabled="folderForm.processing" @click="submitFolder">Create</VibeButton>
            </template>
        </VibeModal>

        <!-- Rename modal -->
        <VibeModal v-model="renameOpen" title="Rename" fullscreen>
            <form @submit.prevent="submitRename">
                <VibeFormGroup
                    label="New Name"
                    :validation-state="renameForm.errors.name ? 'invalid' : null"
                    :validation-message="renameForm.errors.name"
                >
                    <VibeFormInput v-model="renameForm.name" required />
                </VibeFormGroup>
            </form>
            <template #footer>
                <VibeButton variant="secondary" outline @click="renameOpen = false">Cancel</VibeButton>
                <VibeButton variant="primary" :disabled="renameForm.processing" @click="submitRename">Rename</VibeButton>
            </template>
        </VibeModal>

        <!-- Move / Copy modal -->
        <VibeModal v-model="transferOpen" :title="transferMode === 'move' ? 'Move To' : 'Copy To'" fullscreen>
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
                    {{ transferMode === 'move' ? 'Move' : 'Copy' }}
                </VibeButton>
            </template>
        </VibeModal>
    </AppLayout>
</template>
