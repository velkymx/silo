<script setup>
import AppLayout from '../../../Layouts/AppLayout.vue';
import LoadingSkeleton from '../../../Components/LoadingSkeleton.vue';
import PageError from '../../../Components/PageError.vue';
import { ref } from 'vue';
import { usePageLoading } from '../../../composables/usePageLoading';

const props = defineProps({
    logs: { type: Array, default: () => [] },
});

const { loading } = usePageLoading();

const expanded = ref(new Set());
function toggleMeta(id) {
    const next = new Set(expanded.value);
    next.has(id) ? next.delete(id) : next.add(id);
    expanded.value = next;
}

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
        <PageError />
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
                <template v-if="item.meta">
                    <code
                        class="small d-inline-block align-top"
                        :class="expanded.has(item.id) ? 'text-break' : 'text-truncate'"
                        :style="expanded.has(item.id) ? 'white-space: pre-wrap; max-width: 360px' : 'max-width: 220px'"
                    >{{ JSON.stringify(item.meta, null, expanded.has(item.id) ? 2 : 0) }}</code>
                    <VibeButton
                        variant="link"
                        size="sm"
                        class="p-0 ms-1 text-decoration-none"
                        :aria-label="expanded.has(item.id) ? 'Collapse details' : 'Expand details'"
                        @click="toggleMeta(item.id)"
                    >{{ expanded.has(item.id) ? 'less' : 'more' }}</VibeButton>
                </template>
            </template>
        </VibeDataTable>
    </AppLayout>
</template>
