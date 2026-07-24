<script setup>
import { useForm } from '@inertiajs/vue3';
import GuestLayout from '../../Layouts/GuestLayout.vue';
import { fmtBytes } from '../../lib/format';

const props = defineProps({
    locked: { type: Boolean, default: false },
    token: { type: String, required: true },
    name: { type: String, default: '' },
    size: { type: Number, default: 0 },
    type: { type: String, default: '' },
    mime: { type: String, default: '' },
    allow_download: { type: Boolean, default: false },
    previewable: { type: Boolean, default: false },
    preview_url: { type: String, default: '' },
    download_url: { type: String, default: null },
});

const imageTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
const form = useForm({ password: '' });

function unlock() {
    form.post(`/s/${props.token}/unlock`, { onFinish: () => form.reset('password') });
}
</script>

<template>
    <GuestLayout :title="locked ? 'Password required' : name">
        <template v-if="locked">
            <p class="text-muted small">This link is password-protected.</p>
            <form @submit.prevent="unlock">
<VibeFormGroup
                    label="Password"
                    :error="form.errors.password"
                 required>
                    <VibeFormInput v-model="form.password" type="password" required show-toggle />
                </VibeFormGroup>
                <VibeButton type="submit" variant="primary" class="w-100 mt-4" :disabled="form.processing">
                    Unlock
                </VibeButton>
            </form>
        </template>

        <template v-else>
            <div class="text-center mb-3">
                <div v-if="previewable" class="mb-3">
                    <img
                        v-if="imageTypes.includes(type)"
                        :src="preview_url"
                        :alt="name"
                        class="img-fluid rounded border"
                        style="max-height: 50vh"
                    >
                    <iframe
                        v-else
                        :src="preview_url"
                        class="w-100 border rounded"
                        style="height: 50vh"
                    ></iframe>
                </div>
                <div v-else class="text-muted py-4">
                    <VibeIcon icon="file-earmark" class="display-3 d-block mb-2" />
                    No inline preview available.
                </div>
                <div class="small text-muted">{{ mime || 'file' }} · {{ fmtBytes(size) }}</div>
            </div>
            <VibeButton
                v-if="download_url"
                variant="success"
                class="w-100"
                :href="download_url"
            >
                <VibeIcon icon="download" class="me-1" />Download
            </VibeButton>
            <p v-else class="text-center text-muted small mb-0">Downloads are disabled for this link.</p>
        </template>
    </GuestLayout>
</template>
