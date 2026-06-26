<script setup lang="ts">
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppModal from './AppModal.vue';

const open = defineModel<boolean>({ required: true });
const props = defineProps<{ item: { id: number; name: string } | null }>();

const form = useForm({ name: '' });

watch(open, (v) => {
    if (v && props.item) {
        form.clearErrors();
        form.name = props.item.name;
    }
});

function submit(): void {
    if (!props.item) return;
    form.patch(`/files/${props.item.id}/rename`, {
        preserveScroll: true,
        onSuccess: () => { open.value = false; },
    });
}
</script>

<template>
    <AppModal v-model="open" title="Rename" centered>
        <form @submit.prevent="submit">
<AppFormGroup
                label="New Name"
                :error="form.errors.name"
             required>
                <AppFormInput v-model="form.name" required />
            </AppFormGroup>
        </form>
        <template #footer>
            <AppButton variant="secondary" outline @click="open = false">Cancel</AppButton>
            <AppButton variant="primary" :disabled="form.processing" @click="submit">Rename</AppButton>
        </template>
    </AppModal>
</template>
