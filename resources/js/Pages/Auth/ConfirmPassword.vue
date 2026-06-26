<script setup>
import { useForm } from '@inertiajs/vue3';
import GuestLayout from '../../Layouts/GuestLayout.vue';
import AppFormGroup from '../../Components/AppFormGroup.vue';
import FormErrorSummary from '../../Components/FormErrorSummary.vue';

const form = useForm({ password: '' });

function submit() {
    form.post('/password/confirm', { onFinish: () => form.reset() });
}
</script>

<template>
    <GuestLayout title="Confirm password">
        <p class="text-muted small">Please confirm your password before continuing.</p>
        <form @submit.prevent="submit">
<FormErrorSummary :errors="form.errors" />
<AppFormGroup
                label="Password"
                :error="form.errors.password"
             required>
                <AppFormInput v-model="form.password" type="password" required autocomplete="current-password" />
            </AppFormGroup>

            <AppButton type="submit" variant="primary" class="w-100 mt-4" :disabled="form.processing">
                <VibeSpinner v-if="form.processing" size="sm" class="me-1" />{{ form.processing ? 'Confirming…' : 'Confirm' }}
            </AppButton>
        </form>
    </GuestLayout>
</template>
