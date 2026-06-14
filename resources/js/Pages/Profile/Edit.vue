<script setup>
import { computed, ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { Cropper } from 'vue-advanced-cropper';
import 'vue-advanced-cropper/dist/style.css';
import { initials } from '../../lib/initials';

const props = defineProps({
    user: { type: Object, required: true },
    groups: { type: Array, default: () => [] },
});

const groupOptions = computed(() => props.groups.map((g) => ({ value: g.id, text: g.name })));
const initial = computed(() => initials(props.user.name) || '?');

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

// ----- Avatar upload + crop -----
const fileInput = ref(null);
const cropOpen = ref(false);
const cropSrc = ref('');
const cropper = ref(null);
const avatarSaving = ref(false);

function pickPhoto() {
    fileInput.value?.click();
}

function onFileChosen(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = () => {
        cropSrc.value = reader.result;
        cropOpen.value = true;
    };
    reader.readAsDataURL(file);
    e.target.value = '';
}

function applyCrop() {
    const { canvas } = cropper.value.getResult();
    if (!canvas) return;
    avatarSaving.value = true;
    canvas.toBlob((blob) => {
        router.post('/profile/avatar', { avatar: new File([blob], 'avatar.jpg', { type: 'image/jpeg' }) }, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                cropOpen.value = false;
            },
            onFinish: () => {
                avatarSaving.value = false;
            },
        });
    }, 'image/jpeg', 0.9);
}
</script>

<template>
    <AppLayout>
        <VibeRow class="justify-content-center">
            <VibeCol :md="8" :lg="6">
                <VibeCard header="Edit Profile">
                    <div class="d-flex align-items-center gap-3 mb-4">
                        <img
                            v-if="user.avatar_url"
                            :src="user.avatar_url"
                            alt="Avatar"
                            class="rounded-circle border"
                            style="width: 72px; height: 72px; object-fit: cover"
                        >
                        <span
                            v-else
                            class="rounded-circle bg-primary text-white d-inline-flex align-items-center justify-content-center fs-3"
                            style="width: 72px; height: 72px"
                        >{{ initial }}</span>
                        <div>
                            <VibeButton variant="secondary" outline size="sm" @click="pickPhoto">
                                <VibeIcon icon="camera" class="me-1" />Change photo
                            </VibeButton>
                            <div class="small text-muted mt-1">JPG/PNG, square crop.</div>
                        </div>
                        <input ref="fileInput" type="file" accept="image/*" class="d-none" @change="onFileChosen">
                    </div>

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

        <!-- Crop modal -->
        <VibeModal v-model="cropOpen" title="Crop photo" fullscreen hide-footer>
            <Cropper
                ref="cropper"
                :src="cropSrc"
                :stencil-props="{ aspectRatio: 1 }"
                class="bg-body-tertiary"
                style="height: 360px"
            />
            <div class="text-end mt-3">
                <VibeButton variant="secondary" outline class="me-2" @click="cropOpen = false">Cancel</VibeButton>
                <VibeButton variant="primary" :disabled="avatarSaving" @click="applyCrop">Use photo</VibeButton>
            </div>
        </VibeModal>
    </AppLayout>
</template>
