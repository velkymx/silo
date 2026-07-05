<script setup>
import { router } from '@inertiajs/vue3';
import ShellPage from '../../../Components/ShellPage.vue';
import LoadingSkeleton from '../../../Components/LoadingSkeleton.vue';
import { usePageLoading } from '../../../composables/usePageLoading';

defineProps({
    users: { type: Array, default: () => [] },
});

const { loading } = usePageLoading();

const columns = [
    { key: 'name', label: 'Name' },
    { key: 'email', label: 'Email' },
    { key: 'group', label: 'Group', formatter: (v) => v ?? '—' },
    { key: 'is_admin', label: 'Admin' },
    { key: 'actions', label: '', sortable: false, searchable: false },
];

function edit(id) {
    router.visit(`/users/${id}/edit`);
}
</script>

<template>
    <ShellPage title="Users" icon="people" :parents="[{ text: 'Admin', icon: 'shield-lock' }]">
        <LoadingSkeleton v-if="loading" :rows="6" :cols="5" />
        <VibeDataTable v-else :items="users" :columns="columns" row-key="id" hover striped empty-text="No users.">
            <template #cell(is_admin)="{ item }">
                <VibeBadge :variant="item.is_admin ? 'success' : 'secondary'">
                    {{ item.is_admin ? 'Admin' : 'User' }}
                </VibeBadge>
            </template>
            <template #cell(actions)="{ item }">
                <VibeButton variant="primary" size="sm" outline @click="edit(item.id)">
                    <VibeIcon icon="pencil" class="me-1" />Edit
                </VibeButton>
            </template>
        </VibeDataTable>
    </ShellPage>
</template>
