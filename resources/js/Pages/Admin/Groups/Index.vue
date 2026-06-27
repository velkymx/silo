<script setup>
import { ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import LoadingSkeleton from '../../../Components/LoadingSkeleton.vue';
import { useConfirm } from '../../../composables/useConfirm';
import { useToast } from '../../../composables/useToast';
import { usePageLoading } from '../../../composables/usePageLoading';

const { confirm } = useConfirm();
const toast = useToast();
const { loading } = usePageLoading();

defineProps({
    groups: { type: Array, default: () => [] },
});

const createForm = useForm({ name: '' });
const editingId = ref(null);
const editForm = useForm({ name: '' });
const deletingId = ref(null);

const columns = [
    { key: 'name', label: 'Name' },
    { key: 'users_count', label: 'Members' },
    { key: 'actions', label: '', sortable: false, searchable: false },
];

function create() {
    createForm.post('/groups', { preserveScroll: true, onSuccess: () => createForm.reset() });
}

function startEdit(group) {
    editingId.value = group.id;
    editForm.clearErrors();
    editForm.name = group.name;
}

function saveEdit() {
    editForm.patch(`/groups/${editingId.value}`, {
        preserveScroll: true,
        onSuccess: () => (editingId.value = null),
    });
}

async function destroy(group) {
    if (deletingId.value !== null) return;
    if (!await confirm({ title: 'Delete group', message: `Delete group "${group.name}"? Members will be unassigned.`, confirmLabel: 'Delete', variant: 'danger' })) return;
    deletingId.value = group.id;
    router.delete(`/groups/${group.id}`, {
        preserveScroll: true,
        onSuccess: () => toast.push(`Group "${group.name}" deleted`, { variant: 'danger' }),
        onFinish: () => { deletingId.value = null; },
    });
}
</script>

<template>
    <AppLayout>
        <h4 class="mb-3"><VibeIcon icon="people" class="me-2" />Groups</h4>

        <VibeRow class="g-2 align-items-end mb-4">
            <VibeCol :md="6">
<VibeFormGroup
                    label="New group name"
                    :error="createForm.errors.name"
                >
                    <VibeFormInput v-model="createForm.name" @keyup.enter="create" />
                </VibeFormGroup>
            </VibeCol>
            <VibeCol :md="2">
                <VibeButton variant="primary" :disabled="createForm.processing" @click="create"><VibeSpinner v-if="createForm.processing" size="sm" class="me-1" />{{ createForm.processing ? 'Adding…' : 'Add group' }}</VibeButton>
            </VibeCol>
        </VibeRow>

        <LoadingSkeleton v-if="loading" :rows="5" :cols="3" />
        <VibeDataTable
            v-else
            :items="groups"
            :columns="columns"
            row-key="id"
            hover
            striped
            :per-page="25"
            empty-text="No groups yet."
        >
            <template #cell(name)="{ item }">
                <template v-if="editingId === item.id">
                    <div class="d-flex gap-2">
                        <VibeFormInput v-model="editForm.name" no-wrapper @keyup.enter="saveEdit" />
                        <VibeButton variant="success" size="sm" :disabled="editForm.processing" @click="saveEdit"><VibeSpinner v-if="editForm.processing" size="sm" class="me-1" />{{ editForm.processing ? 'Saving…' : 'Save' }}</VibeButton>
                        <VibeButton variant="secondary" size="sm" outline @click="editingId = null">Cancel</VibeButton>
                    </div>
                </template>
                <template v-else>{{ item.name }}</template>
            </template>
            <template #cell(actions)="{ item }">
                <div class="d-flex justify-content-end gap-1">
                    <VibeButton variant="primary" size="sm" outline :aria-label="`Edit ${item.name}`" @click="startEdit(item)">
                        <VibeIcon icon="pencil" />
                    </VibeButton>
                    <VibeButton variant="danger" size="sm" outline :disabled="deletingId === item.id" :aria-label="`Delete ${item.name}`" @click="destroy(item)">
                        <VibeIcon icon="trash" />
                    </VibeButton>
                </div>
            </template>
        </VibeDataTable>
    </AppLayout>
</template>
