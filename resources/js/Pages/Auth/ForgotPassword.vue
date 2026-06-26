<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import GuestLayout from '../../Layouts/GuestLayout.vue';
import AppFormGroup from '../../Components/AppFormGroup.vue';
import FormErrorSummary from '../../Components/FormErrorSummary.vue';

defineProps({ status: { type: String, default: null } });

const form = useForm({ email: '' });

function submit() {
    form.post('/password/email');
}
</script>

<template>
    <GuestLayout title="Reset password">
        <VibeAlert v-if="status" variant="success">{{ status }}</VibeAlert>
        <p class="text-muted small">Enter your email and we'll send a reset link.</p>
        <form @submit.prevent="submit">
<FormErrorSummary :errors="form.errors" />
<AppFormGroup
                label="Email"
                :error="form.errors.email"
             required>
                <AppFormInput v-model="form.email" type="email" required autocomplete="username" />
            </AppFormGroup>

            <AppButton type="submit" variant="primary" class="w-100 mt-4" :disabled="form.processing">
                <VibeSpinner v-if="form.processing" size="sm" class="me-1" />{{ form.processing ? 'Sending…' : 'Send reset link' }}
            </AppButton>

            <div class="text-center mt-3 small">
                <Link href="/login" class="text-decoration-none">Back to sign in</Link>
            </div>
        </form>
    </GuestLayout>
</template>
