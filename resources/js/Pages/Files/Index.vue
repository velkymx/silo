<script setup>
import { ref, computed, watch } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    folders: { type: Array, default: () => [] },
    files: { type: Array, default: () => [] },
    current: { type: Object, default: null },
    breadcrumbs: { type: Array, default: () => [] },
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

// ----- Delete -----
function destroy(id, label) {
    if (!confirm(`Delete "${label}"? This cannot be undone.`)) return;
    router.delete(`/delete/${id}`, { preserveScroll: true });
}

const uploadProgress = computed(() =>
    uploadForm.progress ? [{ value: uploadForm.progress.percentage, showValue: true, variant: 'success' }] : []
);
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
                <VibeButton variant="info" size="sm" @click="visitFolder(item.id)">
                    <VibeIcon icon="box-arrow-in-right" />
                </VibeButton>
                <VibeButton variant="danger" size="sm" outline class="ms-1" @click="destroy(item.id, item.name)">
                    <VibeIcon icon="trash" />
                </VibeButton>
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
            </template>
            <template #cell(actions)="{ item }">
                <VibeButton variant="success" size="sm" :href="`/download/${item.id}`">
                    <VibeIcon icon="download" />
                </VibeButton>
                <VibeButton
                    v-if="imageTypes.includes(item.type)"
                    variant="primary"
                    size="sm"
                    outline
                    class="ms-1"
                    @click="preview(item)"
                >
                    <VibeIcon icon="eye" />
                </VibeButton>
                <VibeButton
                    v-else-if="item.type === 'pdf'"
                    variant="warning"
                    size="sm"
                    outline
                    class="ms-1"
                    :href="item.url"
                >
                    <VibeIcon icon="eye" />
                </VibeButton>
                <VibeButton variant="danger" size="sm" outline class="ms-1" @click="destroy(item.id, item.name)">
                    <VibeIcon icon="trash" />
                </VibeButton>
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
                <VibeProgress v-if="uploadForm.progress" :bars="uploadProgress" class="my-3" />
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
    </AppLayout>
</template>
