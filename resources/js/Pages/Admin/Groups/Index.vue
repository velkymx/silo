<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import LoadingSkeleton from '../../../Components/LoadingSkeleton.vue';
import { useConfirm } from '../../../composables/useConfirm';
import { useToast } from '../../../composables/useToast';
import { usePageLoading } from '../../../composables/usePageLoading';
import AppFormGroup from '../../../Components/AppFormGroup.vue';

const { confirm } = useConfirm();
const toast = useToast();
const { loading } = usePageLoading();

defineProps({
    groups: { type: Array, default: () => [] },
});

const createForm = useForm({ name: '' });
const editingId = ref(null);
const editForm = useForm({ name: '' });

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
    if (!await confirm({ title: 'Delete group', message: `Delete group "${group.name}"? Members will be unassigned.`, confirmLabel: 'Delete', variant: 'danger' })) return;
    useForm({}).delete(`/groups/${group.id}`, {
        preserveScroll: true,
        onSuccess: () => toast.push(`Group "${group.name}" deleted`, { variant: 'danger' }),
    });
}
</script>

<template>
    <AppLayout>
        <h4 class="mb-3"><VibeIcon icon="people" class="me-2" />Groups</h4>

        <VibeRow class="g-2 align-items-end mb-4">
            <VibeCol :md="6">
<AppFormGroup
                    label="New group name"
                    :error="createForm.errors.name"
                >
                    <AppFormInput v-model="createForm.name" @keyup.enter="create" />
                </AppFormGroup>
            </VibeCol>
            <VibeCol :md="2">
                <AppButton variant="primary" :disabled="createForm.processing" @click="create"><VibeSpinner v-if="createForm.processing" size="sm" class="me-1" />{{ createForm.processing ? 'Adding…' : 'Add group' }}</AppButton>
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
                        <AppFormInput v-model="editForm.name" no-wrapper @keyup.enter="saveEdit" />
                        <AppButton variant="success" size="sm" :disabled="editForm.processing" @click="saveEdit"><VibeSpinner v-if="editForm.processing" size="sm" class="me-1" />{{ editForm.processing ? 'Saving…' : 'Save' }}</AppButton>
                        <AppButton variant="secondary" size="sm" outline @click="editingId = null">Cancel</AppButton>
                    </div>
                </template>
                <template v-else>{{ item.name }}</template>
            </template>
            <template #cell(actions)="{ item }">
                <div class="d-flex justify-content-end gap-1">
                    <AppButton variant="primary" size="sm" outline @click="startEdit(item)">
                        <VibeIcon icon="pencil" />
                    </AppButton>
                    <AppButton variant="danger" size="sm" outline @click="destroy(item)">
                        <VibeIcon icon="trash" />
                    </AppButton>
                </div>
            </template>
        </VibeDataTable>
    </AppLayout>
</template>
