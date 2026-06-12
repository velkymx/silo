<script setup>
import { router } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';

const props = defineProps({
    logs: { type: Array, default: () => [] },
    pagination: { type: Object, default: () => ({ current_page: 1, last_page: 1, total: 0 }) },
});

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

function goToPage(page) {
    router.get('/audit', { page }, { preserveScroll: true, preserveState: true });
}
</script>

<template>
    <AppLayout>
        <h4 class="mb-3"><VibeIcon icon="clipboard-check" class="me-2" />Audit Log</h4>
        <p class="text-muted small">{{ pagination.total }} recorded events.</p>

        <VibeDataTable
            :items="logs"
            :columns="columns"
            row-key="id"
            hover
            striped
            small
            :paginated="false"
            empty-text="No audit events yet."
        >
            <template #cell(action)="{ item }">
                <VibeBadge :variant="variant(item.action)">{{ item.action }}</VibeBadge>
            </template>
            <template #cell(meta)="{ item }">
                <code v-if="item.meta" class="small">{{ JSON.stringify(item.meta) }}</code>
            </template>
        </VibeDataTable>

        <div v-if="pagination.last_page > 1" class="mt-3">
            <VibePagination
                :total-pages="pagination.last_page"
                :current-page="pagination.current_page"
                @page-click="goToPage"
            />
        </div>
    </AppLayout>
</template>
