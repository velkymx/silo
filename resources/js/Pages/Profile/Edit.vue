<script setup>
import { computed, ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { Cropper } from 'vue-advanced-cropper';
import 'vue-advanced-cropper/dist/style.css';
import { initials } from '../../lib/initials';
import AppModal from '../../Components/AppModal.vue';
import AppFormGroup from '../../Components/AppFormGroup.vue';

const props = defineProps({
    user: { type: Object, required: true },
    groups: { type: Array, default: () => [] },
});

const initial = computed(() => initials(props.user.name) || '?');

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    password: '',
    password_confirmation: '',
    current_password: '',
    title: props.user.title || '',
    department: props.user.department || '',
    phone: props.user.phone || '',
    location: props.user.location || '',
    bio: props.user.bio || '',
    start_date: props.user.start_date || '',
});

// Changing the email or password requires the current password.
const needsCurrentPassword = computed(() => !!form.password || form.email !== props.user.email);

function submit() {
    form.post('/profile', { onFinish: () => form.reset('password', 'password_confirmation', 'current_password') });
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
                        <AppFormGroup
                            label="Name"
                            :error="form.errors.name"
                         required>
                            <VibeFormInput v-model="form.name" required />
                        </AppFormGroup>

                        <AppFormGroup
                            label="Email"
                            class="mt-3"
                            :error="form.errors.email"
                         required>
                            <VibeFormInput v-model="form.email" type="email" required />
                        </AppFormGroup>

                        <AppFormGroup
                            label="New Password"
                            class="mt-3"
                            :error="form.errors.password"
                        >
                            <VibeFormInput
                                v-model="form.password"
                                type="password"
                                autocomplete="new-password"
                                help-text="Leave blank to keep your current password."
                            />
                        </AppFormGroup>

                        <AppFormGroup label="Confirm Password" class="mt-3">
                            <VibeFormInput v-model="form.password_confirmation" type="password" autocomplete="new-password" />
                        </AppFormGroup>

                        <AppFormGroup
                            v-if="needsCurrentPassword"
                            label="Current Password"
                            class="mt-3"
                            :error="form.errors.current_password"
                        >
                            <VibeFormInput
                                v-model="form.current_password"
                                type="password"
                                autocomplete="current-password"
                                help-text="Required to change your email or password."
                            />
                        </AppFormGroup>

                        <hr class="my-4">
                        <h2 class="h6 text-muted text-uppercase mb-3">Directory profile</h2>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <AppFormGroup label="Job title"><VibeFormInput v-model="form.title" /></AppFormGroup>
                            </div>
                            <div class="col-md-6">
                                <AppFormGroup label="Department"><VibeFormInput v-model="form.department" /></AppFormGroup>
                            </div>
                            <div class="col-md-6">
                                <AppFormGroup label="Phone"><VibeFormInput v-model="form.phone" /></AppFormGroup>
                            </div>
                            <div class="col-md-6">
                                <AppFormGroup label="Location"><VibeFormInput v-model="form.location" /></AppFormGroup>
                            </div>
                            <div class="col-md-6">
                                <AppFormGroup label="Start date"><VibeFormInput v-model="form.start_date" type="date" /></AppFormGroup>
                            </div>
                            <div class="col-12">
                                <AppFormGroup label="About"><VibeFormTextarea v-model="form.bio" :rows="3" /></AppFormGroup>
                            </div>
                        </div>

                        <VibeButton type="submit" variant="primary" class="mt-4" :disabled="form.processing">
                            <VibeSpinner v-if="form.processing" size="sm" class="me-1" />{{ form.processing ? 'Saving…' : 'Save Changes' }}
                        </VibeButton>
                    </form>
                </VibeCard>
            </VibeCol>
        </VibeRow>

        <!-- Crop modal -->
        <AppModal v-model="cropOpen" title="Crop photo" size="lg" centered hide-footer>
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
        </AppModal>
    </AppLayout>
</template>
