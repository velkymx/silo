<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    folders: { type: Array, default: () => [] },
    files: { type: Array, default: () => [] },
    current: { type: Object, default: null },
    breadcrumbs: { type: Array, default: () => [] },
    allFolders: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({ search: '', sort: 'name', direction: 'asc' }) },
});

const currentId = computed(() => props.current?.id ?? null);

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

// ----- Tables -----
const folderColumns = [
    { key: 'name', label: 'Folder' },
    { key: 'item_count', label: 'Items' },
    { key: 'updated_at', label: 'Modified' },
    { key: 'actions', label: '', sortable: false, searchable: false },
];

const fileColumns = [
    { key: 'name', label: 'File Name' },
    { key: 'size', label: 'Size', formatter: (v) => `${(v / 1024).toFixed(2)} KB` },
    { key: 'created_at', label: 'Uploaded' },
    { key: 'actions', label: '', sortable: false, searchable: false },
];

const imageTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

function iconFor(type) {
    if (imageTypes.includes(type)) return 'file-earmark-image';
    if (type === 'pdf') return 'file-earmark-pdf';
    if (['zip', 'rar', '7z', 'tar', 'gz'].includes(type)) return 'file-earmark-zip';
    if (['doc', 'docx', 'txt', 'md'].includes(type)) return 'file-earmark-text';
    return 'file-earmark';
}

const rowActions = [
    { text: 'Rename', action: 'rename', icon: 'pencil' },
    { text: 'Move', action: 'move', icon: 'arrows-move' },
    { text: 'Copy', action: 'copy', icon: 'files' },
    { divider: true },
    { text: 'Delete', action: 'delete', icon: 'trash' },
];

function onAction(item, { item: action }) {
    if (action.action === 'rename') openRename(item);
    if (action.action === 'move') openTransfer(item, 'move');
    if (action.action === 'copy') openTransfer(item, 'copy');
    if (action.action === 'delete') destroy(item);
}

// ----- Preview modal -----
const previewOpen = ref(false);
const previewUrl = ref('');

function preview(file) {
    previewUrl.value = file.url;
    previewOpen.value = true;
}

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

onMounted(startPolling);
onBeforeUnmount(() => poll && clearInterval(poll));
</script>

<template>
    <AppLayout>
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
            </template>
        </VibeInputGroup>

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
                        :items="rowActions"
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

        <h5 class="d-flex align-items-center gap-2"><VibeIcon icon="file-earmark" />Files</h5>
        <VibeDataTable
            :items="files"
            :columns="fileColumns"
            row-key="id"
            hover
            striped
            empty-text="No files here."
        >
            <template #cell(name)="{ item }">
                <VibeIcon :icon="iconFor(item.type)" class="me-1 text-secondary" />{{ item.name }}
                <VibeBadge v-if="item.status === 'pending'" variant="info" class="ms-2">
                    <VibeSpinner size="sm" class="me-1" />Processing
                </VibeBadge>
                <VibeBadge v-else-if="item.status === 'failed'" variant="danger" class="ms-2">Failed</VibeBadge>
                <span v-else-if="item.metadata?.width" class="text-muted small ms-2">
                    {{ item.metadata.width }}×{{ item.metadata.height }}
                </span>
            </template>
            <template #cell(actions)="{ item }">
                <div class="d-flex justify-content-end gap-1">
                    <VibeButton variant="success" size="sm" :href="`/download/${item.id}`">
                        <VibeIcon icon="download" />
                    </VibeButton>
                    <VibeButton
                        v-if="imageTypes.includes(item.type)"
                        variant="primary"
                        size="sm"
                        outline
                        @click="preview(item)"
                    >
                        <VibeIcon icon="eye" />
                    </VibeButton>
                    <VibeButton
                        v-else-if="item.type === 'pdf'"
                        variant="warning"
                        size="sm"
                        outline
                        :href="item.url"
                    >
                        <VibeIcon icon="eye" />
                    </VibeButton>
                    <VibeDropdown
                        size="sm"
                        variant="secondary"
                        menu-end
                        :items="rowActions"
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

        <!-- Preview modal -->
        <VibeModal v-model="previewOpen" title="Preview" centered hide-footer>
            <div class="text-center">
                <img :src="previewUrl" alt="Preview" class="img-fluid">
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
    </AppLayout>
</template>
