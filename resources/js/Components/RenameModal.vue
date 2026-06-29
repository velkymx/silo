<script setup lang="ts">
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { useDirtyGuard } from '../composables/useDirtyGuard';

const open = defineModel<boolean>({ required: true });
const props = defineProps<{ item: { id: number; name: string } | null }>();

const form = useForm({ name: '' });
const { guardedClose } = useDirtyGuard(() => form.isDirty);

watch(open, (v) => {
    if (v && props.item) {
        form.clearErrors();
        form.name = props.item.name;
    }
});

function tryClose() {
    guardedClose(() => { open.value = false; });
}

function submit(): void {
    if (!props.item) return;
    form.patch(`/files/${props.item.id}/rename`, {
        preserveScroll: true,
        onSuccess: () => { open.value = false; },
    });
}
</script>

<template>
    <VibeModal v-model="open" title="Rename" centered>
        <form @submit.prevent="submit">
<VibeFormGroup
                label="New Name"
                :error="form.errors.name"
             required>
                <VibeFormInput v-model="form.name" required />
            </VibeFormGroup>
        </form>
        <template #footer>
            <VibeButton variant="secondary" outline @click="tryClose">Cancel</VibeButton>
            <VibeButton variant="primary" :disabled="form.processing" @click="submit">Rename</VibeButton>
        </template>
    </VibeModal>
</template>
