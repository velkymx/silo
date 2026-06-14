<script setup lang="ts">
import { watch } from 'vue';
import { useForm } from '@inertiajs/vue3';

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
    <VibeModal v-model="open" title="Rename" fullscreen>
        <form @submit.prevent="submit">
            <VibeFormGroup
                label="New Name"
                :validation-state="form.errors.name ? 'invalid' : null"
                :validation-message="form.errors.name"
            >
                <VibeFormInput v-model="form.name" required />
            </VibeFormGroup>
        </form>
        <template #footer>
            <VibeButton variant="secondary" outline @click="open = false">Cancel</VibeButton>
            <VibeButton variant="primary" :disabled="form.processing" @click="submit">Rename</VibeButton>
        </template>
    </VibeModal>
</template>
