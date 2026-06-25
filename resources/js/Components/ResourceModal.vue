<script setup lang="ts">
import { ref } from 'vue';
import AppModal from './AppModal.vue';
import FormErrorSummary from './FormErrorSummary.vue';

const props = defineProps<{
    form: any;
    storeUrl: string;
    updateUrl: (id: number) => string;
    addTitle: string;
    editTitle: string;
    size?: 'sm' | 'lg' | 'xl';
    saveLabel?: string;
    addLabel?: string;
}>();

const emit = defineEmits<{
    'open-edit': [item: any];
    saved: [];
}>();

const showModal = ref(false);
const editingId = ref<number | null>(null);

function openAdd(prefill?: Record<string, unknown>) {
    editingId.value = null;
    props.form.reset();
    props.form.clearErrors();
    if (prefill) Object.assign(props.form, prefill);
    showModal.value = true;
}

function openEdit(item: { id: number } & Record<string, unknown>) {
    editingId.value = item.id;
    props.form.clearErrors();
    emit('open-edit', item);
    showModal.value = true;
}

function cancel() {
    showModal.value = false;
    props.form.reset();
    props.form.clearErrors();
    editingId.value = null;
}

function save() {
    const opts = {
        preserveScroll: true,
        onSuccess: () => {
            showModal.value = false;
            emit('saved');
        },
    };
    if (editingId.value) {
        props.form.put(props.updateUrl(editingId.value), opts);
    } else {
        props.form.post(props.storeUrl, opts);
    }
}

defineExpose({ openAdd, openEdit });
</script>

<template>
    <AppModal v-model="showModal" :title="editingId ? editTitle : addTitle" :size="size">
        <FormErrorSummary :errors="form.errors" />
        <slot :form="form" :editing-id="editingId" />
        <template #footer>
            <VibeButton variant="secondary" outline @click="cancel">Cancel</VibeButton>
            <VibeButton variant="primary" :disabled="form.processing" @click="save">
                {{ editingId ? (saveLabel ?? 'Save') : (addLabel ?? 'Add') }}
            </VibeButton>
        </template>
    </AppModal>
</template>
