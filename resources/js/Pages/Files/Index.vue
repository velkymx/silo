<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import FolderTree from '../../Components/FolderTree.vue';

const props = defineProps({
    folders: { type: Array, default: () => [] },
    files: { type: Array, default: () => [] },
    current: { type: Object, default: null },
    breadcrumbs: { type: Array, default: () => [] },
    allFolders: { type: Array, default: () => [] },
    allTags: { type: Array, default: () => [] },
    searching: { type: Boolean, default: false },
    flat: { type: Boolean, default: false },
    activeTag: { type: Object, default: null },
    pagination: { type: Object, default: () => ({ current_page: 1, last_page: 1, total: 0, per_page: 50 }) },
    filters: { type: Object, default: () => ({ search: '', sort: 'name', direction: 'asc' }) },
});

function goToPage(page) {
    const params = { page };
    if (props.searching) params.search = props.filters.search;
    else if (props.activeTag) params.tag = props.activeTag.id;
    else if (currentId.value) params.folder = currentId.value;
    router.get('/', params, { preserveScroll: true, preserveState: true });
}

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

// ----- Tables -----
const folderColumns = [
    { key: 'name', label: 'Folder' },
    { key: 'item_count', label: 'Items' },
    { key: 'updated_at', label: 'Modified' },
    { key: 'actions', label: '', sortable: false, searchable: false },
];

const fileColumns = computed(() => [
    { key: 'name', label: 'File Name' },
    ...(props.flat ? [{ key: 'location', label: 'Location', sortable: false }] : []),
    { key: 'size', label: 'Size', formatter: (v) => `${(v / 1024).toFixed(2)} KB` },
    { key: 'created_at', label: 'Uploaded' },
    { key: 'actions', label: '', sortable: false, searchable: false },
]);

const imageTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

function iconFor(type) {
    if (imageTypes.includes(type)) return 'file-earmark-image';
    if (type === 'pdf') return 'file-earmark-pdf';
    if (['zip', 'rar', '7z', 'tar', 'gz'].includes(type)) return 'file-earmark-zip';
    if (['doc', 'docx', 'txt', 'md'].includes(type)) return 'file-earmark-text';
    return 'file-earmark';
}

const fileActions = [
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
    if (action.action === 'details') openDetails(item);
    if (action.action === 'share') openShare(item);
    if (action.action === 'tags') openTags(item);
    if (action.action === 'versions') openVersions(item);
    if (action.action === 'rename') openRename(item);
    if (action.action === 'move') openTransfer(item, 'move');
    if (action.action === 'copy') openTransfer(item, 'copy');
    if (action.action === 'delete') destroy(item);
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
      <VibeRow>
        <VibeCol :md="3" :lg="3" class="mb-3">
            <VibeCard header="Folders">
                <VibeButton
                    variant="link"
                    class="p-0 text-decoration-none mb-1 d-block"
                    :class="!current ? 'fw-bold' : 'text-body'"
                    @click="visitFolder(null)"
                >
                    <VibeIcon icon="house-door-fill" class="me-1" />Home
                </VibeButton>
                <FolderTree :folders="allFolders" :current-id="currentId" :open-ids="ancestorIds" />
            </VibeCard>

            <VibeCard v-if="allTags.length" header="Tags" class="mt-3">
                <div class="d-flex flex-wrap gap-1">
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
            </VibeCard>
        </VibeCol>
        <VibeCol :md="9" :lg="9">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
            <VibeBreadcrumb :items="breadcrumbItems" class="mb-0" @item-click="onBreadcrumb" />
            <div class="d-flex gap-2">
                <VibeButton variant="success" @click="uploadOpen = true">
                    <VibeIcon icon="upload" class="me-1" />Upload
                </VibeButton>
                <VibeButton variant="primary" @click="folderOpen = true">
                    <VibeIcon icon="folder-plus" class="me-1" />New Folder
                </VibeButton>
            </div>
        </div>

        <VibeInputGroup class="mb-4">
            <VibeFormInput
                v-model="search"
                type="search"
                placeholder="Search files..."
                no-wrapper
                @keyup.enter="runSearch"
            />
            <template #append>
                <VibeButton variant="primary" @click="runSearch">
                    <VibeIcon icon="search" class="me-1" />Search
                </VibeButton>
                <VibeButton v-if="searching" variant="secondary" outline @click="clearSearch">
                    <VibeIcon icon="x-lg" />
                </VibeButton>
            </template>
        </VibeInputGroup>

        <VibeAlert v-if="searching" variant="info" class="d-flex align-items-center justify-content-between">
            <span><VibeIcon icon="search" class="me-1" />Results for "{{ filters.search }}" across all folders.</span>
            <VibeButton variant="info" size="sm" outline @click="clearSearch">Clear</VibeButton>
        </VibeAlert>

        <VibeAlert v-if="activeTag" variant="primary" class="d-flex align-items-center justify-content-between">
            <span><VibeIcon icon="tag-fill" class="me-1" />Files tagged "{{ activeTag.name }}".</span>
            <VibeButton variant="primary" size="sm" outline @click="filterByTag(null)">Clear</VibeButton>
        </VibeAlert>

        <template v-if="!searching">
            <h5 class="d-flex align-items-center gap-2"><VibeIcon icon="folder" />Folders</h5>
            <VibeDataTable
                :items="folders"
                :columns="folderColumns"
                row-key="id"
                hover
                :searchable="false"
                :paginated="false"
                empty-text="No folders here."
                class="mb-4"
            >
            <template #cell(name)="{ item }">
                <VibeButton variant="link" class="p-0 text-decoration-none" @click="visitFolder(item.id)">
                    <VibeIcon icon="folder-fill" class="me-1 text-warning" />{{ item.name }}
                </VibeButton>
                <span
                    v-for="tag in item.tags"
                    :key="tag.id"
                    class="badge rounded-pill ms-1"
                    :style="{ backgroundColor: tag.color || '#6c757d', cursor: 'pointer' }"
                    @click="filterByTag(tag.id)"
                >{{ tag.name }}</span>
            </template>
            <template #cell(actions)="{ item }">
                <div class="d-flex justify-content-end gap-1">
                    <VibeButton variant="info" size="sm" @click="visitFolder(item.id)">
                        <VibeIcon icon="box-arrow-in-right" />
                    </VibeButton>
                    <VibeDropdown
                        size="sm"
                        variant="secondary"
                        menu-end
                        :items="folderActions"
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
        </template>

        <h5 class="d-flex align-items-center gap-2"><VibeIcon icon="file-earmark" />Files</h5>
        <VibeDataTable
            :items="files"
            :columns="fileColumns"
            row-key="id"
            hover
            striped
            :searchable="false"
            :paginated="false"
            empty-text="No files here."
            @row-clicked="(item, i) => (selectedIndex = i)"
        >
            <template v-if="flat" #cell(location)="{ item }">
                <VibeButton variant="link" class="p-0 text-decoration-none small" @click="visitFolder(item.location.folder_id)">
                    {{ item.location.path }}
                </VibeButton>
            </template>
            <template #cell(name)="{ item }">
                <img
                    v-if="item.thumb_url"
                    :src="item.thumb_url"
                    :alt="item.name"
                    class="rounded border me-2"
                    style="width: 32px; height: 32px; object-fit: cover; cursor: pointer"
                    @click="quickLook(item)"
                >
                <VibeIcon v-else :icon="iconFor(item.type)" class="me-1 text-secondary" />{{ item.name }}
                <VibeBadge v-if="item.status === 'pending'" variant="info" class="ms-2">
                    <VibeSpinner size="sm" class="me-1" />Processing
                </VibeBadge>
                <VibeBadge v-else-if="item.status === 'failed'" variant="danger" class="ms-2">Failed</VibeBadge>
                <span v-else-if="item.metadata?.width" class="text-muted small ms-2">
                    {{ item.metadata.width }}×{{ item.metadata.height }}
                </span>
                <VibeBadge v-if="item.version > 1" variant="secondary" class="ms-2">v{{ item.version }}</VibeBadge>
                <span
                    v-for="tag in item.tags"
                    :key="tag.id"
                    class="badge rounded-pill ms-1"
                    :style="{ backgroundColor: tag.color || '#6c757d', cursor: 'pointer' }"
                    @click="filterByTag(tag.id)"
                >{{ tag.name }}</span>
            </template>
            <template #cell(actions)="{ item }">
                <div class="d-flex justify-content-end gap-1">
                    <VibeButton variant="primary" size="sm" outline title="Quick Look (Space)" @click="quickLook(item)">
                        <VibeIcon icon="eye" />
                    </VibeButton>
                    <VibeButton variant="success" size="sm" :href="`/download/${item.id}`">
                        <VibeIcon icon="download" />
                    </VibeButton>
                    <VibeDropdown
                        size="sm"
                        variant="secondary"
                        menu-end
                        :items="fileActions"
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

        <div v-if="pagination.last_page > 1" class="d-flex justify-content-between align-items-center mt-3">
            <small class="text-muted">{{ pagination.total }} files</small>
            <VibePagination
                :total-pages="pagination.last_page"
                :current-page="pagination.current_page"
                @page-click="goToPage"
            />
        </div>

        <!-- Details modal -->
        <VibeModal v-model="detailsOpen" :title="detailsItem?.name || 'Details'" centered hide-footer>
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
        <VibeModal v-model="shareOpen" :title="`Share — ${shareItem?.name || ''}`" hide-footer>
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
            <p v-else class="text-muted small">Only you (the owner) can access this file.</p>

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
        <VibeModal v-model="tagsOpen" :title="`Tags — ${tagsItem?.name || ''}`" hide-footer>
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
            <div class="text-end mt-3">
                <VibeButton variant="primary" :disabled="tagSaving" @click="saveTags">Save</VibeButton>
            </div>
        </VibeModal>

        <!-- Versions modal -->
        <VibeModal v-model="versionsOpen" :title="`Versions — ${versionsItem?.name || ''}`" centered hide-footer>
            <p class="text-muted small">
                Current: version {{ versionsItem?.version }}.
                <span v-if="!versionsItem?.versions?.length">No previous versions.</span>
            </p>
            <table v-if="versionsItem?.versions?.length" class="table table-sm align-middle mb-0">
                <thead>
                    <tr><th>Version</th><th>Size</th><th>Saved</th><th class="text-end">Actions</th></tr>
                </thead>
                <tbody>
                    <tr v-for="v in versionsItem.versions" :key="v.id">
                        <td>v{{ v.version }}</td>
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
        <VibeModal v-model="quickOpen" size="xl" centered hide-footer>
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
                    </div>
                </div>
            </template>

            <div v-if="quickFile" class="text-center" style="min-height: 50vh">
                <img
                    v-if="imageMime(quickFile)"
                    :src="quickFile.url"
                    :alt="quickFile.name"
                    class="img-fluid rounded"
                    style="max-height: 72vh"
                >
                <iframe
                    v-else-if="quickFile.type === 'pdf'"
                    :src="quickFile.url"
                    class="w-100 border rounded"
                    style="height: 72vh"
                ></iframe>
                <audio v-else-if="quickFile.mime?.startsWith('audio/')" :src="quickFile.url" controls class="w-100 mt-5" />
                <video
                    v-else-if="quickFile.mime?.startsWith('video/')"
                    :src="quickFile.url"
                    controls
                    class="img-fluid rounded"
                    style="max-height: 72vh"
                />
                <pre
                    v-else-if="quickFile.metadata?.preview"
                    class="text-start p-3 bg-body-tertiary rounded border"
                    style="max-height: 72vh; overflow: auto; white-space: pre-wrap"
                >{{ quickFile.metadata.preview }}</pre>
                <div v-else class="text-muted py-5">
                    <VibeIcon :icon="iconFor(quickFile.type)" class="display-1 d-block mb-3" />
                    No inline preview for this file type.
                    <div class="small mt-2">{{ quickFile.mime || 'unknown type' }} · {{ (quickFile.size / 1024).toFixed(1) }} KB</div>
                </div>
            </div>
        </VibeModal>

        <!-- Upload modal -->
        <VibeModal v-model="uploadOpen" title="Upload Files" hide-footer>
            <form @submit.prevent="submitUpload">
                <VibeFileInput
                    v-model="uploadFiles"
                    label="Choose Files"
                    multiple
                    drag-drop
                    help-text="Up to 5 MB per file."
                />
                <p v-if="uploadForm.errors['files.0']" class="text-danger small mt-1">
                    {{ uploadForm.errors['files.0'] }}
                </p>
                <VibeProgress
                    v-if="uploadForm.progress"
                    :bars="[{ value: uploadForm.progress.percentage, showValue: true, variant: 'success' }]"
                    class="my-3"
                />
                <div class="text-end mt-3">
                    <VibeButton
                        type="submit"
                        variant="success"
                        :disabled="uploadForm.processing || !uploadFiles.length"
                    >
                        Upload
                    </VibeButton>
                </div>
            </form>
        </VibeModal>

        <!-- Create folder modal -->
        <VibeModal v-model="folderOpen" title="Create Folder" hide-footer>
            <form @submit.prevent="submitFolder">
                <VibeFormGroup
                    label="Folder Name"
                    :validation-state="folderForm.errors.folder_name ? 'invalid' : null"
                    :validation-message="folderForm.errors.folder_name"
                >
                    <VibeFormInput v-model="folderForm.folder_name" required />
                </VibeFormGroup>
                <div class="text-end mt-3">
                    <VibeButton type="submit" variant="primary" :disabled="folderForm.processing">Create</VibeButton>
                </div>
            </form>
        </VibeModal>

        <!-- Rename modal -->
        <VibeModal v-model="renameOpen" title="Rename" hide-footer>
            <form @submit.prevent="submitRename">
                <VibeFormGroup
                    label="New Name"
                    :validation-state="renameForm.errors.name ? 'invalid' : null"
                    :validation-message="renameForm.errors.name"
                >
                    <VibeFormInput v-model="renameForm.name" required />
                </VibeFormGroup>
                <div class="text-end mt-3">
                    <VibeButton type="submit" variant="primary" :disabled="renameForm.processing">Rename</VibeButton>
                </div>
            </form>
        </VibeModal>

        <!-- Move / Copy modal -->
        <VibeModal v-model="transferOpen" :title="transferMode === 'move' ? 'Move To' : 'Copy To'" hide-footer>
            <form @submit.prevent="submitTransfer">
                <VibeFormGroup
                    label="Destination Folder"
                    :validation-state="transferForm.errors.target_id ? 'invalid' : null"
                    :validation-message="transferForm.errors.target_id"
                >
                    <VibeFormSelect v-model="transferForm.target_id" :options="destinationOptions" />
                </VibeFormGroup>
                <div class="text-end mt-3">
                    <VibeButton type="submit" variant="primary" :disabled="transferForm.processing">
                        {{ transferMode === 'move' ? 'Move' : 'Copy' }}
                    </VibeButton>
                </div>
            </form>
        </VibeModal>
        </VibeCol>
      </VibeRow>
    </AppLayout>
</template>
