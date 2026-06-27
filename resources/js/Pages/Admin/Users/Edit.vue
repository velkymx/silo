<script setup>
import { computed } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import FormErrorSummary from '../../../Components/FormErrorSummary.vue';

const props = defineProps({
    user: { type: Object, required: true },
    groups: { type: Array, default: () => [] },
});

const groupOptions = computed(() => props.groups.map((g) => ({ value: g.id, text: g.name })));

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    group_id: props.user.group_id,
    is_admin: Boolean(props.user.is_admin),
    password: '',
    password_confirmation: '',
});

function submit() {
    form.patch(`/admin/users/${props.user.id}`, {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <AppLayout>
        <VibeRow class="justify-content-center">
            <VibeCol :md="8" :lg="6">
                <VibeCard :header="`Edit User: ${user.name}`">
                    <form @submit.prevent="submit">
                        <FormErrorSummary :errors="form.errors" />
                        <VibeFormGroup
                            label="Name"
                            :error="form.errors.name"
                         required>
                            <VibeFormInput v-model="form.name" required />
                        </VibeFormGroup>

                        <VibeFormGroup
                            label="Email"
                            class="mt-3"
                            :error="form.errors.email"
                         required>
                            <VibeFormInput v-model="form.email" type="email" required />
                        </VibeFormGroup>

                        <VibeFormGroup
                            label="Group"
                            class="mt-3"
                            :error="form.errors.group_id"
                        >
                            <VibeFormSelect v-model="form.group_id" :options="groupOptions" placeholder="Choose…" />
                        </VibeFormGroup>

                        <VibeFormSwitch v-model="form.is_admin" label="Administrator" class="mt-3" />

                        <VibeFormGroup
                            label="New Password"
                            class="mt-3"
                            :error="form.errors.password"
                        >
                            <VibeFormInput
                                v-model="form.password"
                                type="password"
                                autocomplete="new-password"
                                help-text="Leave blank to keep the current password."
                            />
                        </VibeFormGroup>

                        <VibeFormGroup label="Confirm Password" class="mt-3">
                            <VibeFormInput v-model="form.password_confirmation" type="password" autocomplete="new-password" />
                        </VibeFormGroup>

                        <div class="mt-4 d-flex gap-2">
                            <VibeButton type="submit" variant="primary" :disabled="form.processing"><VibeSpinner v-if="form.processing" size="sm" class="me-1" />{{ form.processing ? 'Saving…' : 'Save' }}</VibeButton>
                            <VibeButton variant="secondary" outline @click="router.visit('/users')">Cancel</VibeButton>
                        </div>
                    </form>
                </VibeCard>
            </VibeCol>
        </VibeRow>
    </AppLayout>
</template>
