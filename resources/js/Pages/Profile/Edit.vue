<script setup>
import { computed, ref } from 'vue';
import { useForm, router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { Cropper } from 'vue-advanced-cropper';
import 'vue-advanced-cropper/dist/style.css';
import UserAvatar from '../../Components/UserAvatar.vue';
import FormErrorSummary from '../../Components/FormErrorSummary.vue';

const props = defineProps({
    user: { type: Object, required: true },
    groups: { type: Array, default: () => [] },
});

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
    const canvas = cropper.value?.getResult?.()?.canvas;
    if (!canvas) return;
    avatarSaving.value = true;
    canvas.toBlob((blob) => {
        if (!blob) { avatarSaving.value = false; return; }
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
                        <UserAvatar :user="user" :size="72" class="border" />
                        <div>
                            <VibeButton variant="secondary" outline size="sm" @click="pickPhoto">
                                <VibeIcon icon="camera" class="me-1" />Change photo
                            </VibeButton>
                            <div class="small text-muted mt-1">JPG/PNG, square crop.</div>
                        </div>
                        <input ref="fileInput" type="file" accept="image/*" class="d-none" @change="onFileChosen">
                    </div>

                    <form @submit.prevent="submit">
                        <FormErrorSummary :errors="form.errors" />
                        <VibeFormGroup
                            label="Name"
                            :error="form.errors.name"
                         required>
                            <VibeFormInput v-model="form.name" required />
                        </VibeFormGroup>

                        <VibeFormGroup
                            label="Email"
                            class="mt-3"
                            :error="form.errors.email"
                         required>
                            <VibeFormInput v-model="form.email" type="email" required />
                        </VibeFormGroup>

                        <VibeFormGroup
                            label="New Password"
                            class="mt-3"
                            :error="form.errors.password"
                        >
                            <VibeFormInput
                                v-model="form.password"
                                type="password"
                                autocomplete="new-password"
                                help-text="Leave blank to keep your current password."
                                show-toggle
                                show-password-strength
                            />
                        </VibeFormGroup>

                        <VibeFormGroup label="Confirm Password" class="mt-3">
                            <VibeFormInput v-model="form.password_confirmation" type="password" autocomplete="new-password" show-toggle />
                        </VibeFormGroup>

                        <VibeFormGroup
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
                                show-toggle
                            />
                        </VibeFormGroup>

                        <hr class="my-4">
                        <h2 class="h6 text-muted text-uppercase mb-3">Directory profile</h2>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <VibeFormGroup label="Job title"><VibeFormInput v-model="form.title" /></VibeFormGroup>
                            </div>
                            <div class="col-md-6">
                                <VibeFormGroup label="Department"><VibeFormInput v-model="form.department" /></VibeFormGroup>
                            </div>
                            <div class="col-md-6">
                                <VibeFormGroup label="Phone"><VibeFormInput v-model="form.phone" /></VibeFormGroup>
                            </div>
                            <div class="col-md-6">
                                <VibeFormGroup label="Location"><VibeFormInput v-model="form.location" /></VibeFormGroup>
                            </div>
                            <div class="col-md-6">
                                <VibeFormGroup label="Start date"><VibeFormInput v-model="form.start_date" type="date" /></VibeFormGroup>
                            </div>
                            <div class="col-12">
                                <VibeFormGroup label="About"><VibeFormTextarea v-model="form.bio" :rows="3" /></VibeFormGroup>
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
        <VibeModal v-model="cropOpen" title="Crop photo" size="lg" centered hide-footer>
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
