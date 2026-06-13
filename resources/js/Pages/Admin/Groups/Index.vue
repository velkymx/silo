<script setup>
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';

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

function destroy(group) {
    if (!confirm(`Delete group "${group.name}"? Members will be unassigned.`)) return;
    useForm({}).delete(`/groups/${group.id}`, { preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <h4 class="mb-3"><VibeIcon icon="people" class="me-2" />Groups</h4>

        <VibeRow class="g-2 align-items-end mb-4">
            <VibeCol :md="6">
                <VibeFormGroup
                    label="New group name"
                    :validation-state="createForm.errors.name ? 'invalid' : null"
                    :validation-message="createForm.errors.name"
                >
                    <VibeFormInput v-model="createForm.name" @keyup.enter="create" />
                </VibeFormGroup>
            </VibeCol>
            <VibeCol :md="2">
                <VibeButton variant="primary" :disabled="createForm.processing" @click="create">Add group</VibeButton>
            </VibeCol>
        </VibeRow>

        <VibeDataTable
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
                        <VibeButton variant="success" size="sm" @click="saveEdit">Save</VibeButton>
                        <VibeButton variant="secondary" size="sm" outline @click="editingId = null">Cancel</VibeButton>
                    </div>
                </template>
                <template v-else>{{ item.name }}</template>
            </template>
            <template #cell(actions)="{ item }">
                <div class="d-flex justify-content-end gap-1">
                    <VibeButton variant="primary" size="sm" outline @click="startEdit(item)">
                        <VibeIcon icon="pencil" />
                    </VibeButton>
                    <VibeButton variant="danger" size="sm" outline @click="destroy(item)">
                        <VibeIcon icon="trash" />
                    </VibeButton>
                </div>
            </template>
        </VibeDataTable>
    </AppLayout>
</template>
