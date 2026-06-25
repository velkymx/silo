<script setup>
import { ref, reactive, computed, onBeforeUnmount } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import { http } from '../../lib/http';
import { useConfirm, usePrompt } from '../../composables/useConfirm';

const props = defineProps({
    items: { type: Array, default: () => [] },
    groups: { type: Array, default: () => [] },
});

const { confirm } = useConfirm();
const { prompt } = usePrompt();

const revealError = ref('');

const grouped = computed(() => {
    const map = new Map();
    for (const i of props.items) {
        const key = i.category || 'General';
        if (!map.has(key)) map.set(key, []);
        map.get(key).push(i);
    }
    return [...map.entries()].sort((a, b) => a[0].localeCompare(b[0]));
});

// Currently revealed secrets, keyed by item id (cleared on hide / timeout).
const revealed = reactive({});
const timers = {};

// Clear any pending auto-hide timers so they can't fire after unmount.
onBeforeUnmount(() => {
    Object.values(timers).forEach((id) => clearTimeout(id));
});

async function reveal(item) {
    if (revealed[item.id]) return hide(item.id);
    const password = await prompt({
        title: 'Confirm your password',
        message: `Re-enter your password to reveal “${item.name}”.`,
        confirmLabel: 'Reveal',
    });
    if (!password) return;
    revealError.value = '';
    try {
        const data = await http.post(`/vault/${item.id}/reveal`, { password });
        revealed[item.id] = data.secret;
        // Auto-hide after 20s so secrets don't linger on screen.
        clearTimeout(timers[item.id]);
        timers[item.id] = setTimeout(() => hide(item.id), 20000);
    } catch (e) {
        // 422 = wrong password; surface it in-app, not via a native alert().
        revealError.value = e?.data?.errors?.password?.[0] || 'Could not reveal secret.';
    }
}

function hide(id) {
    delete revealed[id];
    clearTimeout(timers[id]);
}

function copy(id) {
    if (revealed[id]) navigator.clipboard?.writeText(revealed[id]);
}

// ----- Add / edit -----
const showModal = ref(false);
const editingId = ref(null);
const form = useForm({ name: '', username: '', url: '', category: '', secret: '', notes: '', group_id: '' });

function openAdd() {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    showModal.value = true;
}

function openEdit(item) {
    editingId.value = item.id;
    form.clearErrors();
    // The secret is never sent to the client; leave it blank (blank = keep existing).
    Object.assign(form, {
        name: item.name, username: item.username || '', url: item.url || '',
        category: item.category || '', secret: '', notes: '', group_id: item.group_id || '',
    });
    showModal.value = true;
}

function save() {
    const opts = { preserveScroll: true, onSuccess: () => { showModal.value = false; } };
    if (editingId.value) form.put(`/vault/${editingId.value}`, opts);
    else form.post('/vault', opts);
}

async function generate() {
    const data = await http.get('/vault/generate?length=20');
    form.secret = data.password;
}

async function remove(item) {
    if (await confirm({ title: 'Remove secret', message: `Remove “${item.name}”?`, confirmLabel: 'Remove', variant: 'danger' })) {
        router.delete(`/vault/${item.id}`, { preserveScroll: true });
    }
}

const importInput = ref(null);
async function onImportFile(e) {
    const file = e.target.files?.[0];
    e.target.value = '';
    if (!file) return;
    if (!await confirm({
        title: 'Import passwords',
        message: 'Import this Chrome password CSV? Secrets are encrypted on save. Delete the CSV file afterwards.',
        confirmLabel: 'Import',
    })) return;
    router.post('/vault/import', { file }, { forceFormData: true, preserveScroll: true });
}
</script>

<template>
    <AppLayout>
        <div class="p-3 p-lg-4">
            <div class="d-flex align-items-center justify-content-between mb-3">
                <h1 class="h4 mb-0"><VibeIcon icon="lock-fill" class="text-primary me-2" />Vault</h1>
                <div class="d-flex gap-2">
                    <VibeButton variant="secondary" outline title="Import a Chrome password CSV export" @click="importInput?.click()">
                        <VibeIcon icon="upload" class="me-1" />Import
                    </VibeButton>
                    <VibeButton variant="primary" @click="openAdd"><VibeIcon icon="plus-lg" class="me-1" />Add secret</VibeButton>
                    <input ref="importInput" type="file" accept=".csv,text/csv" class="d-none" @change="onImportFile">
                </div>
            </div>

            <VibeAlert v-if="revealError" variant="danger" dismissible class="mb-3" @dismiss="revealError = ''">
                {{ revealError }}
            </VibeAlert>

            <p v-if="!items.length" class="text-muted">No secrets yet.</p>

            <div v-for="[category, list] in grouped" :key="category" class="mb-4">
                <div class="text-uppercase small text-muted fw-semibold mb-2">{{ category }}</div>
                <div class="list-group">
                    <div v-for="item in list" :key="item.id" class="list-group-item d-flex align-items-center gap-3">
                        <VibeIcon icon="shield-lock" class="text-muted fs-5" />
                        <div class="flex-grow-1 min-vw-0">
                            <div class="fw-semibold text-truncate">
                                {{ item.name }}
                                <VibeBadge v-if="item.shared" class="text-bg-light ms-1"><VibeIcon icon="people-fill" /></VibeBadge>
                            </div>
                            <div class="small text-muted text-truncate">
                                <span v-if="item.username">{{ item.username }} · </span>
                                <code v-if="revealed[item.id]" class="vault-secret">{{ revealed[item.id] }}</code>
                                <span v-else>••••••••••</span>
                            </div>
                        </div>
                        <VibeButton size="sm" variant="secondary" outline :title="revealed[item.id] ? 'Hide' : 'Reveal'" @click="reveal(item)">
                            <VibeIcon :icon="revealed[item.id] ? 'eye-slash' : 'eye'" />
                        </VibeButton>
                        <VibeButton v-if="revealed[item.id]" size="sm" variant="secondary" outline title="Copy" @click="copy(item.id)">
                            <VibeIcon icon="clipboard" />
                        </VibeButton>
                        <VibeButton v-if="item.can_edit" size="sm" variant="light" title="Edit" @click="openEdit(item)">
                            <VibeIcon icon="pencil" />
                        </VibeButton>
                        <VibeButton v-if="item.can_edit" size="sm" variant="light" title="Remove" @click="remove(item)">
                            <VibeIcon icon="trash" />
                        </VibeButton>
                    </div>
                </div>
            </div>
        </div>

        <VibeModal v-model="showModal" :title="editingId ? 'Edit secret' : 'Add secret'">
            <VibeFormGroup label="Name" :error="form.errors.name"><VibeFormInput v-model="form.name" placeholder="AWS root" /></VibeFormGroup>
            <div class="row">
                <div class="col-6"><VibeFormGroup label="Username" :error="form.errors.username"><VibeFormInput v-model="form.username" /></VibeFormGroup></div>
                <div class="col-6"><VibeFormGroup label="Category" :error="form.errors.category"><VibeFormInput v-model="form.category" /></VibeFormGroup></div>
            </div>
            <VibeFormGroup label="URL" :error="form.errors.url"><VibeFormInput v-model="form.url" placeholder="https://…" /></VibeFormGroup>
            <VibeFormGroup :label="editingId ? 'Secret (blank = keep current)' : 'Secret'" :error="form.errors.secret">
                <div class="d-flex gap-2">
                    <VibeFormInput v-model="form.secret" class="flex-grow-1" />
                    <VibeButton variant="secondary" outline title="Generate" @click="generate"><VibeIcon icon="shuffle" /></VibeButton>
                </div>
            </VibeFormGroup>
            <VibeFormGroup label="Share with group" :error="form.errors.group_id">
                <VibeFormSelect v-model="form.group_id">
                    <option value="">Private (only me)</option>
                    <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
                </VibeFormSelect>
            </VibeFormGroup>
            <template #footer>
                <VibeButton variant="secondary" outline @click="showModal = false">Cancel</VibeButton>
                <VibeButton variant="primary" :disabled="form.processing" @click="save">{{ editingId ? 'Save' : 'Add' }}</VibeButton>
            </template>
        </VibeModal>
    </AppLayout>
</template>

<style scoped>
.vault-secret {
    user-select: all;
}
</style>
