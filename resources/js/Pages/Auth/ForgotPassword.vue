<script setup>
import { ref, computed } from 'vue';
import { useForm, Link } from '@inertiajs/vue3';
import GuestLayout from '../../Layouts/GuestLayout.vue';

defineProps({ status: { type: String, default: null } });

const COOLDOWN_KEY = 'fp_cooldown_until';
const COOLDOWN_MS = 30_000;

// Re-hydrate the cooldown timer from localStorage so a refresh doesn't
// bypass the throttle.
const cooldownUntil = ref(Number(localStorage.getItem(COOLDOWN_KEY) || 0));
const now = ref(Date.now());
setInterval(() => { now.value = Date.now(); }, 1000);
const cooldownRemaining = computed(() => Math.max(0, cooldownUntil.value - now.value));

const form = useForm({ email: '' });
const justSent = ref(false);

function submit() {
    if (cooldownRemaining.value > 0) return;
    cooldownUntil.value = Date.now() + COOLDOWN_MS;
    try { localStorage.setItem(COOLDOWN_KEY, String(cooldownUntil.value)); } catch { /* blocked */ }
    justSent.value = true;
    form.post('/password/email');
}
</script>

<template>
    <GuestLayout title="Reset password">
        <VibeAlert v-if="status" variant="success">{{ status }}</VibeAlert>
        <p class="text-muted small">Enter your email and we'll send a reset link.</p>
        <form @submit.prevent="submit">
<VibeFormGroup
                label="Email"
                :error="form.errors.email"
             required>
                <VibeFormInput v-model="form.email" type="email" required autocomplete="username" />
            </VibeFormGroup>

            <VibeButton type="submit" variant="primary" class="w-100 mt-4" :disabled="form.processing || cooldownRemaining > 0">
                <VibeSpinner v-if="form.processing" size="sm" class="me-1" />{{ form.processing ? 'Sending…' : (cooldownRemaining > 0 ? `Wait ${Math.ceil(cooldownRemaining / 1000)}s` : 'Send reset link') }}
            </VibeButton>

            <div class="text-center mt-3 small">
                <Link href="/login" class="text-decoration-none">Back to sign in</Link>
            </div>
        </form>
    </GuestLayout>
</template>
