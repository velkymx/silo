<script setup>
import { ref, reactive, computed, onBeforeUnmount } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import PageHeader from '../../Components/PageHeader.vue';
import SelectBar from '../../Components/SelectBar.vue';
import { http } from '../../lib/http';
import { useConfirm, usePrompt } from '../../composables/useConfirm';
import { useToast } from '../../composables/useToast';
import { useSelection } from '../../composables/useSelection';
import ResourceModal from '../../Components/ResourceModal.vue';
import EmptyState from '../../Components/EmptyState.vue';
import LoadingSkeleton from '../../Components/LoadingSkeleton.vue';
import { usePageLoading } from '../../composables/usePageLoading';

const props = defineProps({
    items: { type: Array, default: () => [] },
    groups: { type: Array, default: () => [] },
});

const { confirm } = useConfirm();
const toast = useToast();
const { prompt } = usePrompt();
const { loading } = usePageLoading();

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

async function copy(id) {
    if (!revealed[id]) return;
    try {
        await navigator.clipboard.writeText(revealed[id]);
        toast.push('Copied to clipboard', { variant: 'success' });
    } catch {
        toast.push('Could not copy — clipboard access denied', { variant: 'danger' });
    }
}

// ----- Add / edit -----
const vaultModal = ref(null);
const form = useForm({ name: '', username: '', url: '', category: '', secret: '', notes: '', group_id: '' });

function openAdd() {
    vaultModal.value?.openAdd();
}

function openEdit(item) {
    // The secret is never sent to the client; leave it blank (blank = keep existing).
    Object.assign(form, {
        name: item.name, username: item.username || '', url: item.url || '',
        category: item.category || '', secret: '', notes: '', group_id: item.group_id || '',
    });
    vaultModal.value?.openEdit(item);
}

async function generate() {
    try {
        const data = await http.get('/vault/generate?length=20');
        form.secret = data.password;
    } catch {
        revealError.value = 'Could not generate password. Try again.';
    }
}

async function remove(item) {
    if (!await confirm({ title: 'Remove secret', message: `Remove “${item.name}”?`, confirmLabel: 'Remove', variant: 'danger' })) return;
    router.delete(`/vault/${item.id}`, {
        preserveScroll: true,
        onSuccess: () => toast.push(`”${item.name}” removed`, { variant: 'danger' }),
    });
}

// ----- Multi-select -----
const vaultItemsRef = computed(() => props.items);
const { selectMode: vaultSelectMode, selectedItems: selectedVaultItems, isSelected: vaultIsSelected, toggleSel: vaultToggle, clearSelection: vaultClearSel } = useSelection(vaultItemsRef, openEdit);

async function bulkDeleteVault() {
    if (!await confirm({ title: `Remove ${selectedVaultItems.value.length} secrets`, message: 'Permanently remove the selected secrets?', confirmLabel: 'Remove', variant: 'danger' })) return;
    for (const item of selectedVaultItems.value) {
        router.delete(`/vault/${item.id}`, { preserveScroll: true });
    }
    toast.push(`${selectedVaultItems.value.length} secret(s) removed`, { variant: 'danger' });
    vaultClearSel();
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
        <LoadingSkeleton v-if="loading" :rows="6" :cols="3" />
        <div v-else class="p-3 p-lg-4">
            <PageHeader title="Vault" icon="lock-fill">
                <template #actions>
                    <VibeButton
                        :variant="vaultSelectMode ? 'primary' : 'secondary'"
                        outline
                        title="Select secrets"
                        @click="vaultSelectMode = !vaultSelectMode"
                    >
                        <VibeIcon icon="check2-square" class="me-1" />Select
                    </VibeButton>
                    <VibeButton variant="secondary" outline title="Import a Chrome password CSV export" @click="importInput?.click()">
                        <VibeIcon icon="upload" class="me-1" />Import
                    </VibeButton>
                    <VibeButton variant="primary" @click="openAdd"><VibeIcon icon="plus-lg" class="me-1" />Add secret</VibeButton>
                    <input ref="importInput" type="file" accept=".csv,text/csv" class="d-none" @change="onImportFile">
                </template>
            </PageHeader>

            <VibeAlert v-if="revealError" variant="danger" dismissible class="mb-3" @dismiss="revealError = ''">
                {{ revealError }}
            </VibeAlert>

            <SelectBar :count="selectedVaultItems.length" class="mb-3" @clear="vaultClearSel">
                <VibeButton variant="danger" size="sm" outline @click="bulkDeleteVault">
                    <VibeIcon icon="trash" class="me-1" />Remove
                </VibeButton>
            </SelectBar>

            <EmptyState v-if="!items.length" icon="key-fill" title="No secrets yet" hint="Add a password or API key to get started." />

            <div v-for="[category, list] in grouped" :key="category" class="mb-4">
                <div class="text-uppercase small text-muted fw-semibold mb-2">{{ category }}</div>
                <div class="list-group">
                    <div
                        v-for="item in list"
                        :key="item.id"
                        class="list-group-item d-flex align-items-center gap-3"
                        :class="{ 'bg-primary-subtle': vaultIsSelected(item.id) }"
                    >
                        <button
                            v-if="vaultSelectMode"
                            type="button"
                            class="btn btn-sm p-0 lh-1 bg-transparent border-0"
                            :aria-pressed="vaultIsSelected(item.id)"
                            :aria-label="vaultIsSelected(item.id) ? `Deselect ${item.name}` : `Select ${item.name}`"
                            @click="vaultToggle(item.id)"
                        >
                            <VibeIcon
                                :icon="vaultIsSelected(item.id) ? 'check-circle-fill' : 'circle'"
                                :class="vaultIsSelected(item.id) ? 'text-primary' : 'text-muted'"
                            />
                        </button>
                        <VibeIcon v-else icon="shield-lock" class="text-muted fs-5" />
                        <div class="flex-grow-1 min-vw-0">
                            <div class="fw-semibold text-truncate">
                                {{ item.name }}
                                <VibeBadge v-if="item.shared" class="text-bg-light ms-1"><VibeIcon icon="people-fill" /></VibeBadge>
                            </div>
                            <div class="small text-muted text-truncate">
                                <span v-if="item.username">{{ item.username }}</span>
                                <span v-if="!revealed[item.id]">
                                    <span v-if="item.username"> · </span>••••••••••
                                </span>
                            </div>
                            <pre v-if="revealed[item.id]" class="vault-secret mb-0 small">{{ revealed[item.id] }}</pre>
                        </div>
                        <VibeButton size="sm" variant="secondary" outline :aria-label="revealed[item.id] ? 'Hide secret' : 'Reveal secret'" @click="reveal(item)">
                            <VibeIcon :icon="revealed[item.id] ? 'eye-slash' : 'eye'" class="me-1" />{{ revealed[item.id] ? 'Hide' : 'Reveal' }}
                        </VibeButton>
                        <VibeButton v-if="revealed[item.id]" size="sm" variant="secondary" outline aria-label="Copy secret to clipboard" @click="copy(item.id)">
                            <VibeIcon icon="clipboard" class="me-1" />Copy
                        </VibeButton>
                        <VibeDropdown
                            v-if="item.can_edit"
                            size="sm"
                            variant="light"
                            menu-end
                            aria-label="Secret actions"
                            :items="[{ text: 'Edit', action: 'edit', icon: 'pencil' }, { text: 'Remove', action: 'remove', icon: 'trash', class: 'text-danger' }]"
                            @item-click="$event.item.action === 'edit' ? openEdit(item) : remove(item)"
                        >
                            <template #button><VibeIcon icon="three-dots-vertical" /><span class="visually-hidden">Secret actions</span></template>
                            <template #item="{ item: a }"><VibeIcon :icon="a.icon" :class="['me-2', a.class || '']" />{{ a.text }}</template>
                        </VibeDropdown>
                    </div>
                </div>
            </div>
        </div>

        <ResourceModal
            ref="vaultModal"
            :form="form"
            store-url="/vault"
            :update-url="(id) => `/vault/${id}`"
            add-title="Add secret"
            edit-title="Edit secret"
        >
            <template #default="{ editingId }">
                <VibeFormGroup label="Name" :error="form.errors.name"><VibeFormInput v-model="form.name" placeholder="AWS root" /></VibeFormGroup>
                <div class="row">
                    <div class="col-6"><VibeFormGroup label="Username" :error="form.errors.username"><VibeFormInput v-model="form.username" /></VibeFormGroup></div>
                    <div class="col-6"><VibeFormGroup label="Category" :error="form.errors.category"><VibeFormInput v-model="form.category" /></VibeFormGroup></div>
                </div>
                <VibeFormGroup label="URL" :error="form.errors.url"><VibeFormInput v-model="form.url" placeholder="https://…" /></VibeFormGroup>
                <VibeFormGroup :label="editingId ? 'Secret (blank = keep current)' : 'Secret'" :error="form.errors.secret">
                    <div class="d-flex gap-2">
                        <VibeFormInput v-model="form.secret" class="flex-grow-1" />
                        <VibeButton variant="secondary" outline title="Generate" aria-label="Generate password" @click="generate"><VibeIcon icon="shuffle" /></VibeButton>
                    </div>
                </VibeFormGroup>
                <VibeFormGroup label="Share with group" :error="form.errors.group_id">
                    <VibeFormSelect v-model="form.group_id">
                        <option value="">Private (only me)</option>
                        <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
                    </VibeFormSelect>
                </VibeFormGroup>
            </template>
        </ResourceModal>
    </AppLayout>
</template>

<style scoped>
.vault-secret {
    user-select: all;
    overflow-x: auto;
    white-space: pre-wrap;
    word-break: break-all;
    background: none;
    border: none;
    padding: 0;
    font-family: var(--bs-font-monospace);
}
</style>
