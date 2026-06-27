<script setup>
import { useForm, Link } from '@inertiajs/vue3';
import GuestLayout from '../../Layouts/GuestLayout.vue';
import FormErrorSummary from '../../Components/FormErrorSummary.vue';

const form = useForm({ name: '', email: '', password: '', password_confirmation: '' });

function submit() {
    form.post('/register', { onFinish: () => form.reset('password', 'password_confirmation') });
}
</script>

<template>
    <GuestLayout title="Create account">
        <form @submit.prevent="submit">
<FormErrorSummary :errors="form.errors" />
<VibeFormGroup
                label="Name"
                :error="form.errors.name"
             required>
                <VibeFormInput v-model="form.name" required autocomplete="name" />
            </VibeFormGroup>

<VibeFormGroup
                label="Email"
                class="mt-3"
                :error="form.errors.email"
             required>
                <VibeFormInput v-model="form.email" type="email" required autocomplete="username" />
            </VibeFormGroup>

<VibeFormGroup
                label="Password"
                class="mt-3"
                :error="form.errors.password"
             required>
                <VibeFormInput v-model="form.password" type="password" required autocomplete="new-password" show-toggle show-password-strength />
            </VibeFormGroup>

            <VibeFormGroup label="Confirm Password" class="mt-3" required>
                <VibeFormInput
                    v-model="form.password_confirmation"
                    type="password"
                    required
                    autocomplete="new-password"
                    show-toggle
                />
            </VibeFormGroup>

            <VibeButton type="submit" variant="primary" class="w-100 mt-4" :disabled="form.processing">
                <VibeSpinner v-if="form.processing" size="sm" class="me-1" />{{ form.processing ? 'Creating account…' : 'Register' }}
            </VibeButton>

            <div class="text-center mt-3 small">
                <Link href="/login" class="text-decoration-none">Already have an account? Sign in</Link>
            </div>
        </form>
    </GuestLayout>
</template>
