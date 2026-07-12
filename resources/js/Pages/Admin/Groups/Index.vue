<script setup>
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import ShellPage from '../../../Components/ShellPage.vue';
import LoadingSkeleton from '../../../Components/LoadingSkeleton.vue';
import { useConfirm, usePrompt } from '../../../composables/useConfirm';
import { useToast } from '../../../composables/useToast';
import { usePageLoading } from '../../../composables/usePageLoading';

const { confirm } = useConfirm();
const { prompt } = usePrompt();
const toast = useToast();
const { loading } = usePageLoading();

defineProps({
    groups: { type: Array, default: () => [] },
});

const busy = ref(false);

const columns = [
    { key: 'name', label: 'Name' },
    { key: 'users_count', label: 'Members' },
    { key: 'actions', label: '', sortable: false, searchable: false },
];

// House pattern for single-field resources (Files/Notes folders): a topbar
// CTA opening a prompt dialog — no always-visible inline form, no inline
// row-edit state.
async function create() {
    const name = await prompt({ title: 'New group', message: 'Group name:', confirmLabel: 'Create' });
    if (!name || !name.trim()) return;
    busy.value = true;
    router.post('/groups', { name: name.trim() }, {
        preserveScroll: true,
        onSuccess: () => toast.push(`Group "${name.trim()}" created`, { variant: 'success' }),
        onError: (errors) => toast.push(errors.name ?? 'Could not create the group.', { variant: 'danger' }),
        onFinish: () => { busy.value = false; },
    });
}

async function rename(group) {
    const name = await prompt({ title: 'Rename group', message: 'Group name:', value: group.name, confirmLabel: 'Rename' });
    if (!name || !name.trim() || name.trim() === group.name) return;
    router.patch(`/groups/${group.id}`, { name: name.trim() }, {
        preserveScroll: true,
        onSuccess: () => toast.push(`Group renamed to "${name.trim()}"`, { variant: 'success' }),
        onError: (errors) => toast.push(errors.name ?? 'Could not rename the group.', { variant: 'danger' }),
    });
}

async function destroy(group) {
    if (busy.value) return;
    if (!await confirm({ title: 'Delete group', message: `Delete group "${group.name}"? Members will be unassigned.`, confirmLabel: 'Delete', variant: 'danger' })) return;
    busy.value = true;
    router.delete(`/groups/${group.id}`, {
        preserveScroll: true,
        onSuccess: () => toast.push(`Group "${group.name}" deleted`, { variant: 'danger' }),
        onFinish: () => { busy.value = false; },
    });
}
</script>

<template>
    <ShellPage title="Groups" icon="people" :parents="[{ text: 'Admin', icon: 'shield-lock' }]">
        <template #actions>
            <VibeButton variant="primary" size="sm" :disabled="busy" data-testid="new-group" @click="create">
                <VibeIcon icon="plus-lg" class="me-1" />New group
            </VibeButton>
        </template>

        <LoadingSkeleton v-if="loading" :rows="5" :cols="3" />
        <VibeDataTable
            v-else
            :items="groups"
            :columns="columns"
            row-key="id"
            hover
            striped
            :searchable="false"
            :per-page="25"
            empty-text="No groups yet."
        >
            <template #cell(actions)="{ item }">
                <div class="d-flex justify-content-end gap-1">
                    <VibeButton variant="primary" size="sm" outline :aria-label="`Rename ${item.name}`" @click="rename(item)">
                        <VibeIcon icon="pencil" />
                    </VibeButton>
                    <VibeButton variant="danger" size="sm" outline :disabled="busy" :aria-label="`Delete ${item.name}`" @click="destroy(item)">
                        <VibeIcon icon="trash" />
                    </VibeButton>
                </div>
            </template>
        </VibeDataTable>
    </ShellPage>
</template>
