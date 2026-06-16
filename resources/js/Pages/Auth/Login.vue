<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import GuestLayout from '../../Layouts/GuestLayout.vue';

const form = useForm({ email: '', password: '', remember: false });

function submit() {
    form.post('/login', { onFinish: () => form.reset('password') });
}
</script>

<template>
    <GuestLayout title="Sign in">
        <form @submit.prevent="submit">
            <VibeFormGroup
                label="Email"
                :validation-state="form.errors.email ? 'invalid' : null"
                :validation-message="form.errors.email"
            >
                <VibeFormInput v-model="form.email" type="email" required autocomplete="username" />
            </VibeFormGroup>

            <VibeFormGroup
                label="Password"
                class="mt-3"
                :validation-state="form.errors.password ? 'invalid' : null"
                :validation-message="form.errors.password"
            >
                <VibeFormInput v-model="form.password" type="password" required autocomplete="current-password" />
            </VibeFormGroup>

            <VibeFormCheckbox v-model="form.remember" label="Remember me" class="mt-3" />

            <VibeButton type="submit" variant="primary" class="w-100 mt-4" :disabled="form.processing">
                <VibeSpinner v-if="form.processing" size="sm" class="me-1" />{{ form.processing ? 'Signing in…' : 'Sign in' }}
            </VibeButton>

            <div class="d-flex justify-content-between mt-3 small">
                <Link href="/password/reset" class="text-decoration-none">Forgot password?</Link>
                <Link href="/register" class="text-decoration-none">Create account</Link>
            </div>
        </form>
    </GuestLayout>
</template>
