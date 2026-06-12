<script setup>
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';

const props = defineProps({
    user: { type: Object, required: true },
    groups: { type: Array, default: () => [] },
});

const groupOptions = computed(() => props.groups.map((g) => ({ value: g.id, text: g.name })));

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    group_id: props.user.group_id,
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post('/profile', { onFinish: () => form.reset('password', 'password_confirmation') });
}
</script>

<template>
    <AppLayout>
        <VibeRow class="justify-content-center">
            <VibeCol :md="8" :lg="6">
                <VibeCard header="Edit Profile">
                    <form @submit.prevent="submit">
                        <VibeFormGroup
                            label="Name"
                            :validation-state="form.errors.name ? 'invalid' : null"
                            :validation-message="form.errors.name"
                        >
                            <VibeFormInput v-model="form.name" required />
                        </VibeFormGroup>

                        <VibeFormGroup
                            label="Email"
                            class="mt-3"
                            :validation-state="form.errors.email ? 'invalid' : null"
                            :validation-message="form.errors.email"
                        >
                            <VibeFormInput v-model="form.email" type="email" required />
                        </VibeFormGroup>

                        <VibeFormGroup
                            label="Group"
                            class="mt-3"
                            :validation-state="form.errors.group_id ? 'invalid' : null"
                            :validation-message="form.errors.group_id"
                        >
                            <VibeFormSelect v-model="form.group_id" :options="groupOptions" placeholder="Choose…" />
                        </VibeFormGroup>

                        <VibeFormGroup
                            label="New Password"
                            class="mt-3"
                            :validation-state="form.errors.password ? 'invalid' : null"
                            :validation-message="form.errors.password"
                        >
                            <VibeFormInput
                                v-model="form.password"
                                type="password"
                                autocomplete="new-password"
                                help-text="Leave blank to keep your current password."
                            />
                        </VibeFormGroup>

                        <VibeFormGroup label="Confirm Password" class="mt-3">
                            <VibeFormInput v-model="form.password_confirmation" type="password" autocomplete="new-password" />
                        </VibeFormGroup>

                        <VibeButton type="submit" variant="primary" class="mt-4" :disabled="form.processing">
                            Save Changes
                        </VibeButton>
                    </form>
                </VibeCard>
            </VibeCol>
        </VibeRow>
    </AppLayout>
</template>
