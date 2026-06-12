<script setup>
import { useForm } from '@inertiajs/vue3';
import GuestLayout from '../../Layouts/GuestLayout.vue';

const form = useForm({ password: '' });

function submit() {
    form.post('/password/confirm', { onFinish: () => form.reset() });
}
</script>

<template>
    <GuestLayout title="Confirm password">
        <p class="text-muted small">Please confirm your password before continuing.</p>
        <form @submit.prevent="submit">
            <VibeFormGroup
                label="Password"
                :validation-state="form.errors.password ? 'invalid' : null"
                :validation-message="form.errors.password"
            >
                <VibeFormInput v-model="form.password" type="password" required autocomplete="current-password" />
            </VibeFormGroup>

            <VibeButton type="submit" variant="primary" class="w-100 mt-4" :disabled="form.processing">
                Confirm
            </VibeButton>
        </form>
    </GuestLayout>
</template>
