<script setup>
import { useForm } from '@inertiajs/vue3';
import GuestLayout from '../../Layouts/GuestLayout.vue';
import AppFormGroup from '../../Components/AppFormGroup.vue';
import FormErrorSummary from '../../Components/FormErrorSummary.vue';

const props = defineProps({
    token: { type: String, required: true },
    email: { type: String, default: '' },
});

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
});

function submit() {
    form.post('/password/reset', { onFinish: () => form.reset('password', 'password_confirmation') });
}
</script>

<template>
    <GuestLayout title="Set new password">
        <form @submit.prevent="submit">
<FormErrorSummary :errors="form.errors" />
<AppFormGroup
                label="Email"
                :error="form.errors.email"
             required>
                <AppFormInput v-model="form.email" type="email" required autocomplete="username" />
            </AppFormGroup>

<AppFormGroup
                label="New Password"
                class="mt-3"
                :error="form.errors.password"
             required>
                <AppFormInput v-model="form.password" type="password" required autocomplete="new-password" />
            </AppFormGroup>

            <AppFormGroup label="Confirm Password" class="mt-3" required>
                <AppFormInput
                    v-model="form.password_confirmation"
                    type="password"
                    required
                    autocomplete="new-password"
                />
            </AppFormGroup>

            <AppButton type="submit" variant="primary" class="w-100 mt-4" :disabled="form.processing">
                <VibeSpinner v-if="form.processing" size="sm" class="me-1" />{{ form.processing ? 'Resetting…' : 'Reset password' }}
            </AppButton>
        </form>
    </GuestLayout>
</template>
