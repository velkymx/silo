<script setup>
import { computed } from 'vue';
import { useForm, router, usePage } from '@inertiajs/vue3';
import ShellPage from '../../../Components/ShellPage.vue';
import { useConfirm } from '../../../composables/useConfirm';

const props = defineProps({
    user: { type: Object, required: true },
    groups: { type: Array, default: () => [] },
});

const page = usePage();
const isSelf = computed(() => page.props.auth?.user?.id === props.user.id);
const groupOptions = computed(() => props.groups.map((g) => ({ value: g.id, text: g.name })));
const { confirm } = useConfirm();

const form = useForm({
    name: props.user.name,
    email: props.user.email,
    group_id: props.user.group_id,
    is_admin: Boolean(props.user.is_admin),
    disabled: Boolean(props.user.disabled),
    quota_mb: props.user.quota_mb ?? null,
    password: '',
    password_confirmation: '',
});

async function submit() {
    if (isSelf.value && !form.is_admin) {
        const ok = await confirm({
            title: 'Remove your own admin access?',
            message: 'You will be locked out of the admin area on save. Continue?',
            confirmLabel: 'Demote myself',
            variant: 'danger',
        });
        if (!ok) return;
    }
    form.patch(`/admin/users/${props.user.id}`, {
        onFinish: () => form.reset('password', 'password_confirmation'),
    });
}
</script>

<template>
    <ShellPage title="Edit User" icon="person-gear" :parents="[{ text: 'Admin', icon: 'shield-lock' }, { text: 'Users', icon: 'people' }]">
        <VibeRow class="justify-content-center">
            <VibeCol :md="8" :lg="6">
                <VibeCard :header="`Edit User: ${user.name}`">
                    <form @submit.prevent="submit">
                        <VibeFormGroup
                            label="Name"
                            :error="form.errors.name"
                            required
                        >
                            <VibeFormInput v-model="form.name" required />
                        </VibeFormGroup>

                        <VibeFormGroup
                            label="Email"
                            class="mt-3"
                            :error="form.errors.email"
                            required
                        >
                            <VibeFormInput v-model="form.email" type="email" required />
                        </VibeFormGroup>

                        <VibeFormGroup
                            label="Group"
                            class="mt-3"
                            :error="form.errors.group_id"
                        >
                            <VibeFormSelect v-model="form.group_id" :options="groupOptions" placeholder="Choose…" />
                        </VibeFormGroup>

                        <VibeFormSwitch v-model="form.is_admin" label="Administrator" class="mt-3" />
                        <div v-if="isSelf && !form.is_admin" class="alert alert-warning mt-2 py-2 small" role="alert">
                            Warning: removing your own admin access will lock you out of this area.
                        </div>

                        <VibeFormSwitch v-model="form.disabled" label="Disabled" class="mt-3" :disabled="isSelf" />
                        <div v-if="form.errors.disabled" class="text-danger small mt-1">{{ form.errors.disabled }}</div>
                        <div v-if="form.disabled" class="alert alert-warning mt-2 py-2 small" role="alert">
                            A disabled account cannot log in, and any live session ends on its next request.
                        </div>

                        <VibeFormGroup
                            label="Storage quota (MB)"
                            class="mt-3"
                            :error="form.errors.quota_mb"
                        >
                            <VibeFormInput
                                v-model.number="form.quota_mb"
                                type="number"
                                min="0"
                                help-text="Blank = the server default. 0 = unlimited."
                            />
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
                                help-text="Leave blank to keep the current password."
                                show-toggle
                            />
                        </VibeFormGroup>

                        <VibeFormGroup label="Confirm Password" class="mt-3">
                            <VibeFormInput v-model="form.password_confirmation" type="password" autocomplete="new-password" show-toggle />
                        </VibeFormGroup>

                        <div class="mt-4 d-flex gap-2">
                            <VibeButton type="submit" variant="primary" :disabled="form.processing"><VibeSpinner v-if="form.processing" size="sm" class="me-1" />{{ form.processing ? 'Saving…' : 'Save' }}</VibeButton>
                            <VibeButton variant="secondary" outline @click="router.visit('/users')">Cancel</VibeButton>
                        </div>
                    </form>
                </VibeCard>
            </VibeCol>
        </VibeRow>
    </ShellPage>
</template>
