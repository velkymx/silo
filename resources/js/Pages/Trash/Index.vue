<script setup>
import { router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import LoadingSkeleton from '../../Components/LoadingSkeleton.vue';
import PageError from '../../Components/PageError.vue';
import { useConfirm } from '../../composables/useConfirm';
import { usePageLoading } from '../../composables/usePageLoading';

const { confirm } = useConfirm();
const { loading } = usePageLoading();

const props = defineProps({
    items: { type: Array, default: () => [] },
});

const columns = [
    { key: 'name', label: 'Name' },
    { key: 'deleted_at', label: 'Deleted' },
    { key: 'actions', label: '', sortable: false, searchable: false },
];

function iconFor(item) {
    if (item.is_dir) return 'folder-fill';
    if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(item.type)) return 'file-earmark-image';
    if (item.type === 'pdf') return 'file-earmark-pdf';
    return 'file-earmark';
}

function restore(item) {
    router.post(`/trash/${item.id}/restore`, {}, { preserveScroll: true });
}

async function purge(item) {
    if (!await confirm({ title: 'Permanently delete', message: `Permanently delete "${item.name}"? This cannot be undone.`, confirmLabel: 'Delete', variant: 'danger' })) return;
    router.delete(`/trash/${item.id}`, { preserveScroll: true });
}

async function emptyTrash() {
    if (!await confirm({ title: 'Empty trash', message: 'Permanently delete everything in the trash? This cannot be undone.', confirmLabel: 'Empty trash', variant: 'danger' })) return;
    router.delete('/trash/empty', { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <PageError />
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0"><VibeIcon icon="trash" class="me-2" />Trash</h4>
            <VibeButton v-if="items.length" variant="danger" outline @click="emptyTrash">
                <VibeIcon icon="trash" class="me-1" />Empty Trash
            </VibeButton>
        </div>
        <p class="text-muted small">Items are permanently removed after the retention period.</p>

        <LoadingSkeleton v-if="loading" :rows="6" :cols="3" />
        <VibeDataTable
            v-else
            :items="items"
            :columns="columns"
            row-key="id"
            hover
            striped
            :per-page="25"
            empty-text="Trash is empty."
        >
            <template #cell(name)="{ item }">
                <VibeIcon :icon="iconFor(item)" class="me-2" :class="item.is_dir ? 'text-warning' : 'text-secondary'" />
                {{ item.name }}
            </template>
            <template #cell(actions)="{ item }">
                <div class="d-flex justify-content-end gap-1">
                    <VibeButton variant="success" size="sm" outline @click="restore(item)">
                        <VibeIcon icon="arrow-counterclockwise" class="me-1" />Restore
                    </VibeButton>
                    <VibeButton variant="danger" size="sm" outline @click="purge(item)">
                        <VibeIcon icon="trash" />
                    </VibeButton>
                </div>
            </template>
        </VibeDataTable>
    </AppLayout>
</template>
