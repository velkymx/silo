<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { fmtBytes } from '../lib/format';
import { iconFor } from '../lib/fileTypes';
import QuickLookModal from './QuickLookModal.vue';

const props = defineProps({
    folders: { type: Array, default: () => [] },
    files: { type: Array, default: () => [] },
});

function openFolder(id) {
    router.get(`/shared/${id}`, {}, { preserveScroll: true });
}

const folderColumns = [
    { key: 'name', label: 'Folder' },
    { key: 'owner', label: 'Owner' },
    { key: 'abilities', label: 'Access', sortable: false },
    { key: 'actions', label: '', sortable: false, searchable: false },
];

const fileColumns = [
    { key: 'name', label: 'File Name' },
    { key: 'owner', label: 'Owner' },
    { key: 'size', label: 'Size', formatter: (v) => fmtBytes(v) },
    { key: 'abilities', label: 'Access', sortable: false },
    { key: 'actions', label: '', sortable: false, searchable: false },
];

const previewOpen = ref(false);
const previewFile = ref(null);
const canPreview = (f) => f && (['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'].includes(f.type));

function quickLook(file) {
    previewFile.value = file;
    previewOpen.value = true;
}
</script>

<template>
    <h5 class="d-flex align-items-center gap-2"><VibeIcon icon="folder" />Folders</h5>
    <VibeDataTable
        :items="folders"
        :columns="folderColumns"
        row-key="id"
        hover
        :searchable="false"
        :per-page="25"
        empty-text="No shared folders."
        class="mb-4"
    >
        <template #cell(name)="{ item }">
            <VibeButton variant="link" class="p-0 text-decoration-none" @click="openFolder(item.id)">
                <VibeIcon icon="folder-fill" class="me-1 text-warning" />{{ item.name }}
            </VibeButton>
        </template>
        <template #cell(abilities)="{ item }">
            <VibeBadge v-for="a in item.abilities" :key="a" variant="secondary" class="me-1">{{ a }}</VibeBadge>
        </template>
        <template #cell(actions)="{ item }">
            <VibeButton variant="info" size="sm" @click="openFolder(item.id)">
                <VibeIcon icon="box-arrow-in-right" class="me-1" />Open
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
        :searchable="false"
        :per-page="25"
        empty-text="No shared files."
    >
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
        </template>
        <template #cell(abilities)="{ item }">
            <VibeBadge v-for="a in item.abilities" :key="a" variant="secondary" class="me-1">{{ a }}</VibeBadge>
        </template>
        <template #cell(actions)="{ item }">
            <div class="d-flex justify-content-end gap-1">
                <VibeButton
                    v-if="canPreview(item)"
                    variant="primary"
                    size="sm"
                    outline
                    title="Quick Look"
                    :aria-label="`Quick Look ${item.name}`"
                    @click="quickLook(item)"
                >
                    <VibeIcon icon="eye" />
                </VibeButton>
                <VibeButton variant="success" size="sm" :href="`/download/${item.id}`" :aria-label="`Download ${item.name}`">
                    <VibeIcon icon="download" />
                </VibeButton>
            </div>
        </template>
    </VibeDataTable>

    <QuickLookModal
        v-model="previewOpen"
        :file="previewFile"
        :index="0"
        :total="1"
        :menu="[]"
        @step="previewOpen = false"
    />
</template>
