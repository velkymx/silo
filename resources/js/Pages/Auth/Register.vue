<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import GuestLayout from '../../Layouts/GuestLayout.vue';

const form = useForm({ name: '', email: '', password: '', password_confirmation: '' });

function submit() {
    form.post('/register', { onFinish: () => form.reset('password', 'password_confirmation') });
}
</script>

<template>
    <GuestLayout title="Create account">
        <form @submit.prevent="submit">
            <VibeFormGroup
                label="Name"
                :validation-state="form.errors.name ? 'invalid' : null"
                :validation-message="form.errors.name"
            >
                <VibeFormInput v-model="form.name" required autocomplete="name" />
            </VibeFormGroup>

            <VibeFormGroup
                label="Email"
                class="mt-3"
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
                <VibeFormInput v-model="form.password" type="password" required autocomplete="new-password" />
            </VibeFormGroup>

            <VibeFormGroup label="Confirm Password" class="mt-3">
                <VibeFormInput
                    v-model="form.password_confirmation"
                    type="password"
                    required
                    autocomplete="new-password"
                />
            </VibeFormGroup>

            <VibeButton type="submit" variant="primary" class="w-100 mt-4" :disabled="form.processing">
                Register
            </VibeButton>

            <div class="text-center mt-3 small">
                <Link href="/login" class="text-decoration-none">Already have an account? Sign in</Link>
            </div>
        </form>
    </GuestLayout>
</template>
