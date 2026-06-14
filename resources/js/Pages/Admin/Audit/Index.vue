<script setup>
import AppLayout from '../../../Layouts/AppLayout.vue';
import LoadingSkeleton from '../../../Components/LoadingSkeleton.vue';
import { usePageLoading } from '../../../composables/usePageLoading';

const props = defineProps({
    logs: { type: Array, default: () => [] },
});

const { loading } = usePageLoading();

const columns = [
    { key: 'at', label: 'When' },
    { key: 'user', label: 'User' },
    { key: 'action', label: 'Action' },
    { key: 'file', label: 'File' },
    { key: 'ip', label: 'IP' },
    { key: 'meta', label: 'Detail', sortable: false },
];

const variant = (action) => {
    if (action.includes('purge') || action.includes('trash') || action.includes('revoke')) return 'danger';
    if (action.includes('grant') || action.includes('create') || action.includes('upload')) return 'success';
    if (action.includes('download')) return 'info';
    return 'secondary';
};
</script>

<template>
    <AppLayout>
        <h4 class="mb-3"><VibeIcon icon="clipboard-check" class="me-2" />Audit Log</h4>
        <p class="text-muted small">{{ logs.length }} recent events.</p>

        <LoadingSkeleton v-if="loading" :rows="8" :cols="6" />
        <VibeDataTable
            v-else
            :items="logs"
            :columns="columns"
            row-key="id"
            hover
            striped
            small
            searchable
            :per-page="25"
            empty-text="No audit events yet."
        >
            <template #cell(action)="{ item }">
                <VibeBadge :variant="variant(item.action)">{{ item.action }}</VibeBadge>
            </template>
            <template #cell(meta)="{ item }">
                <code v-if="item.meta" class="small">{{ JSON.stringify(item.meta) }}</code>
            </template>
        </VibeDataTable>
    </AppLayout>
</template>
