<script setup>
import { ref, reactive, computed, onMounted, onBeforeUnmount } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import ShellLayout from '../../Layouts/ShellLayout.vue';
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

// ----- Shell panes -----
const activePane = ref('contents');

// ----- Folders (categories) -----
const selectedFolder = ref(null); // null = All
const folders = computed(() =>
    [...new Set(props.items.map((i) => i.category || 'General'))].sort()
);
const counts = computed(() => {
    const map = {};
    for (const i of props.items) {
        const key = i.category || 'General';
        map[key] = (map[key] ?? 0) + 1;
    }
    return map;
});

function pickFolder(f) {
    selectedFolder.value = f;
    clearContentSelection();
    activePane.value = 'contents';
}

const listed = computed(() => {
    if (selectedFolder.value === null) return props.items;
    return props.items.filter((i) => (i.category || 'General') === selectedFolder.value);
});

// ----- Explorer table -----
const tableColumns = [
    { key: '_select', label: '', sortable: false, searchable: false, headerClass: 'vt-col-select', class: 'vt-col-select' },
    { key: 'name', label: 'Name', class: 'vt-col-name' },
    { key: 'username', label: 'Username', headerClass: 'd-none d-lg-table-cell', class: 'd-none d-lg-table-cell vt-col-meta' },
    { key: 'category', label: 'Folder', headerClass: 'd-none d-xl-table-cell', class: 'd-none d-xl-table-cell vt-col-meta' },
    { key: '_actions', label: '', sortable: false, searchable: false, headerClass: 'vt-col-actions', class: 'vt-col-actions' },
];
const tableItems = computed(() => listed.value.map((i) => ({ ...i, category: i.category || 'General' })));
const listPage = ref(1);

// ----- Breadcrumb -----
const breadcrumbItems = computed(() => [
    { text: 'Vault', folder: null, href: '/vault', active: selectedFolder.value === null },
    ...(selectedFolder.value !== null
        ? [{ text: selectedFolder.value, folder: selectedFolder.value, href: '/vault', active: true }]
        : []),
]);
function onBreadcrumb({ item, event }) {
    event?.preventDefault?.();
    if (!item.active) pickFolder(item.folder);
}

// ----- Selection / detail -----
const selectedId = ref(null);
const selectedItem = computed(() => props.items.find((i) => i.id === selectedId.value) || null);

function selectItem(id) {
    selectedId.value = id;
    activePane.value = 'detail';
}

// ----- Reveal / copy (unchanged security flows) -----
// Currently revealed secrets, keyed by item id (cleared on hide / timeout).
const revealed = reactive({});
const timers = {};

onBeforeUnmount(() => {
    Object.values(timers).forEach((id) => clearTimeout(id));
    window.removeEventListener('keydown', onKey);
});

async function reveal(item) {
    if (item.id in revealed) return hide(item.id);
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
    if (!(id in revealed)) return;
    const secret = revealed[id];
    try {
        await navigator.clipboard.writeText(secret);
        // Hide the secret from the DOM immediately so it doesn't linger on
        // screen while the user moves to another task.
        hide(id);
        toast.push('Copied to clipboard', { variant: 'success' });
        // Auto-clear the clipboard 30s later so a walk-away leaves nothing
        // in the OS clipboard. The writeText is the same shape, so it just
        // replaces the contents with an empty string.
        setTimeout(() => {
            navigator.clipboard?.writeText('').catch(() => { /* ignore */ });
        }, 30_000);
    } catch {
        toast.push('Could not copy — clipboard access denied', { variant: 'danger' });
    }
}

// ----- Multi-select (hover checkbox column) -----
const vaultItemsRef = computed(() => props.items);
const { selectedIds: vaultSelectedIds, selectedItems: selectedVaultItems, isSelected: vaultIsSelected, toggleSel: vaultToggle, clearSelection: vaultClearSel } = useSelection(vaultItemsRef, (i) => selectItem(i.id));

function clearContentSelection() {
    vaultClearSel();
    selectedId.value = null;
    if (activePane.value === 'detail') activePane.value = 'contents';
}

function onKey(e) {
    const tag = (e.target?.tagName || '').toLowerCase();
    const type = (e.target?.type || '').toLowerCase();
    const inTextField = (['input', 'textarea', 'select'].includes(tag) && !['checkbox', 'radio', 'button'].includes(type))
        || !!e.target?.isContentEditable;
    if (!inTextField && e.key === 'Escape') clearContentSelection();
}
onMounted(() => window.addEventListener('keydown', onKey));

async function bulkDeleteVault() {
    if (!await confirm({ title: `Delete ${selectedVaultItems.value.length} secrets`, message: 'Delete the selected secrets? This cannot be undone.', confirmLabel: 'Delete', variant: 'danger' })) return;
    for (const item of selectedVaultItems.value) {
        router.delete(`/vault/${item.id}`, { preserveScroll: true });
    }
    toast.push(`${selectedVaultItems.value.length} secret(s) removed`, { variant: 'danger' });
    clearContentSelection();
}

// ----- Add / edit -----
const vaultModal = ref(null);
const form = useForm({ name: '', username: '', url: '', category: '', secret: '', notes: '', group_id: '' });

function openAdd() {
    if (selectedFolder.value && selectedFolder.value !== 'General') form.category = selectedFolder.value;
    vaultModal.value?.openAdd();
}

function openEdit(item) {
    // The secret is never sent to the client; leave it blank (blank = keep existing).
    Object.assign(form, {
        name: item.name, username: item.username || '', url: item.url || '',
        category: item.category || '', secret: '', notes: item.notes || '', group_id: item.group_id || '',
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
    if (!await confirm({ title: 'Delete secret', message: `Delete “${item.name}”? This cannot be undone.`, confirmLabel: 'Delete', variant: 'danger' })) return;
    router.delete(`/vault/${item.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            if (selectedId.value === item.id) clearContentSelection();
            toast.push(`”${item.name}” removed`, { variant: 'danger' });
        },
    });
}

const rowMenu = [
    { text: 'Edit', action: 'edit', icon: 'pencil' },
    { divider: true },
    { text: 'Delete', action: 'delete', icon: 'trash' },
];
function onRowMenu(item, { item: action }) {
    if (action.action === 'edit') openEdit(item);
    if (action.action === 'delete') remove(item);
}

// ----- Import -----
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
    <ShellLayout v-model:active-pane="activePane" :detail-visible="!!selectedItem">
        <template #viewNav>
            <div class="d-flex flex-column p-2 w-100">
                <div class="d-flex align-items-center justify-content-between px-1 mb-1">
                    <span class="fw-semibold small text-uppercase text-muted">Folders</span>
                </div>
                <button
                    type="button"
                    class="side-row w-100 text-start d-flex align-items-center gap-2 px-2 py-1 rounded border-0 bg-transparent"
                    :class="{ active: selectedFolder === null }"
                    @click="pickFolder(null)"
                >
                    <VibeIcon icon="lock-fill" />
                    <span class="flex-grow-1">All Secrets</span>
                </button>
                <button
                    v-for="folder in folders"
                    :key="folder"
                    type="button"
                    class="side-row w-100 text-start d-flex align-items-center gap-2 px-2 py-1 rounded border-0 bg-transparent"
                    :class="{ active: selectedFolder === folder }"
                    @click="pickFolder(folder)"
                >
                    <VibeIcon :icon="folder === 'General' ? 'folder' : 'folder-fill'" class="text-warning" />
                    <span class="flex-grow-1 text-truncate">{{ folder }}</span>
                    <span class="badge text-bg-light">{{ counts[folder] ?? 0 }}</span>
                </button>
            </div>
        </template>

        <!-- Breadcrumb + actions on one top-bar line: Add secret is the single
             solid-primary CTA; Import is a quiet light ghost. -->
        <template #topBar>
            <div class="d-flex align-items-center gap-2 p-1">
                <VibeBreadcrumb :items="breadcrumbItems" class="breadcrumb mb-0 pb-0 text-truncate min-w-0" @item-click="onBreadcrumb">
                    <template #item="{ item, index }">
                        <VibeIcon :icon="index === 0 ? 'lock-fill' : 'folder2'" class="me-1" /><span :title="item.text">{{ item.text }}</span>
                    </template>
                </VibeBreadcrumb>
                <div class="d-flex align-items-center gap-2 ms-auto flex-shrink-0">
                    <VibeButton size="sm" variant="primary" title="Add secret" aria-label="Add secret" @click="openAdd">
                        <VibeIcon icon="plus-lg" />
                    </VibeButton>
                    <VibeButton size="sm" variant="light" title="Import a Chrome password CSV export" aria-label="Import passwords" @click="importInput?.click()">
                        <VibeIcon icon="upload" />
                    </VibeButton>
                    <input ref="importInput" type="file" accept=".csv,text/csv" class="d-none" @change="onImportFile">
                </div>
            </div>
        </template>

        <template #contents>
            <VibeAlert v-if="revealError" variant="danger" dismissible class="mx-2 mt-2 mb-0" @dismiss="revealError = ''">
                {{ revealError }}
            </VibeAlert>

            <SelectBar :count="selectedVaultItems.length" class="mx-2 mt-2" @clear="vaultClearSel">
                <VibeButton variant="danger" size="sm" outline @click="bulkDeleteVault">
                    <VibeIcon icon="trash" class="me-1" />Delete
                </VibeButton>
            </SelectBar>

            <LoadingSkeleton v-if="loading" :rows="6" :cols="3" />

            <div
                v-else
                class="vt-table-wrap overflow-auto flex-grow-1 px-2 pt-1"
                :class="{ 'vt-has-selection': vaultSelectedIds.size > 0 }"
                @click.self="clearContentSelection"
            >
                <VibeDataTable
                    v-if="listed.length"
                    :items="tableItems"
                    :columns="tableColumns"
                    row-key="id"
                    hover
                    small
                    clickable
                    search-placeholder="Search secrets…"
                    :show-per-page="false"
                    :per-page="50"
                    v-model:current-page="listPage"
                    sort-by="name"
                    class="vt-table"
                    @row-clicked="(i) => selectItem(i.id)"
                >
                    <template #cell(_select)="{ item }">
                        <VibeFormCheckbox
                            class="vt-select-check"
                            :model-value="vaultIsSelected(item.id)"
                            :aria-label="vaultIsSelected(item.id) ? `Deselect ${item.name}` : `Select ${item.name}`"
                            @update:model-value="vaultToggle(item.id)"
                            @click.stop
                        />
                    </template>
                    <template #cell(name)="{ item }">
                        <div class="d-flex align-items-center min-w-0">
                            <VibeIcon icon="shield-lock" class="me-2 text-muted flex-shrink-0" />
                            <span class="text-truncate" :title="item.name">{{ item.name }}</span>
                            <VibeBadge v-if="item.shared" class="text-bg-light ms-2 flex-shrink-0"><VibeIcon icon="people-fill" /></VibeBadge>
                        </div>
                    </template>
                    <template #cell(username)="{ item }">
                        <span class="text-muted small text-truncate d-inline-block vt-username" :title="item.username">{{ item.username }}</span>
                    </template>
                    <template #cell(category)="{ item }">
                        <span class="text-muted small text-nowrap">{{ item.category }}</span>
                    </template>
                    <template #cell(_actions)="{ item }">
                        <VibeDropdown v-if="item.can_edit" size="sm" variant="light" menu-end title="Secret actions" :items="rowMenu" @click.stop @item-click="onRowMenu(item, $event)">
                            <template #button><VibeIcon icon="three-dots-vertical" /><span class="visually-hidden">Secret actions</span></template>
                            <template #item="{ item: a }"><VibeIcon :icon="a.icon" class="me-2" />{{ a.text }}</template>
                        </VibeDropdown>
                    </template>
                </VibeDataTable>
                <EmptyState v-else icon="key-fill" title="No secrets yet" hint="Add a password or API key to get started." />
            </div>
        </template>

        <template #detail>
            <div v-if="selectedItem" class="d-flex flex-column h-100 p-3 overflow-auto">
                <div class="d-flex align-items-center gap-3 mb-3 flex-shrink-0">
                    <span class="vt-detail-icon d-inline-flex align-items-center justify-content-center rounded">
                        <VibeIcon icon="shield-lock" class="fs-3" />
                    </span>
                    <div class="min-w-0">
                        <div class="h5 mb-0 text-break">{{ selectedItem.name }}</div>
                        <div class="small text-muted">
                            {{ selectedItem.category || 'General' }}
                            <VibeBadge v-if="selectedItem.shared" class="text-bg-light ms-1"><VibeIcon icon="people-fill" class="me-1" />Shared</VibeBadge>
                        </div>
                    </div>
                </div>

                <dl class="row small mb-3 flex-shrink-0">
                    <template v-if="selectedItem.username">
                        <dt class="col-4 text-muted fw-normal">Username</dt>
                        <dd class="col-8 text-break mb-1">{{ selectedItem.username }}</dd>
                    </template>
                    <template v-if="selectedItem.url">
                        <dt class="col-4 text-muted fw-normal">URL</dt>
                        <dd class="col-8 text-break mb-1">
                            <a :href="selectedItem.url" target="_blank" rel="noopener">{{ selectedItem.url }}</a>
                        </dd>
                    </template>
                    <template v-if="selectedItem.notes">
                        <dt class="col-4 text-muted fw-normal">Notes</dt>
                        <dd class="col-8 text-break mb-1">{{ selectedItem.notes }}</dd>
                    </template>
                </dl>

                <!-- Secret: masked until revealed; auto-hides after 20s. -->
                <div class="mb-3 flex-shrink-0">
                    <div class="small text-muted mb-1">Secret</div>
                    <input
                        v-if="selectedItem.id in revealed && revealed[selectedItem.id]"
                        type="text"
                        readonly
                        :value="revealed[selectedItem.id]"
                        class="form-control form-control-sm vault-secret"
                        @focus="$event.target.select()"
                    >
                    <span v-else-if="selectedItem.id in revealed" class="small text-muted">(empty secret)</span>
                    <span v-else class="text-muted">••••••••••</span>
                </div>

                <div class="d-flex flex-wrap gap-2 mt-auto flex-shrink-0">
                    <VibeButton variant="primary" :aria-label="(selectedItem.id in revealed) ? 'Hide secret' : 'Reveal secret'" @click="reveal(selectedItem)">
                        <VibeIcon :icon="(selectedItem.id in revealed) ? 'eye-slash' : 'eye'" class="me-1" />{{ (selectedItem.id in revealed) ? 'Hide' : 'Reveal' }}
                    </VibeButton>
                    <VibeButton v-if="selectedItem.id in revealed" variant="secondary" outline aria-label="Copy secret to clipboard" @click="copy(selectedItem.id)">
                        <VibeIcon icon="clipboard" class="me-1" />Copy
                    </VibeButton>
                    <template v-if="selectedItem.can_edit">
                        <VibeButton variant="secondary" outline @click="openEdit(selectedItem)"><VibeIcon icon="pencil" class="me-1" />Edit</VibeButton>
                        <VibeButton variant="danger" outline @click="remove(selectedItem)"><VibeIcon icon="trash" class="me-1" />Delete</VibeButton>
                    </template>
                </div>
            </div>
        </template>
    </ShellLayout>

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
            <VibeFormGroup :label="editingId ? 'Notes (blank = keep current)' : 'Notes'" :error="form.errors.notes">
                <VibeFormInput v-model="form.notes" type="textarea" rows="3" placeholder="Optional notes…" />
            </VibeFormGroup>
            <VibeFormGroup label="Share with group" :error="form.errors.group_id">
                <VibeFormSelect v-model="form.group_id">
                    <option value="">Private (only me)</option>
                    <option v-for="g in groups" :key="g.id" :value="g.id">{{ g.name }}</option>
                </VibeFormSelect>
            </VibeFormGroup>
        </template>
    </ResourceModal>
</template>

<style scoped>
.min-w-0 {
    min-width: 0;
}
.vt-username {
    max-width: 16rem;
    vertical-align: middle;
}
/* Explorer table chrome (mirrors the Files table). */
.vt-table-wrap :deep(thead th) {
    position: sticky;
    top: 0;
    z-index: 1;
    background: var(--bs-body-bg);
}
.vt-table-wrap :deep(td) {
    vertical-align: middle;
}
.vt-table-wrap :deep(.vt-col-select),
.vt-table-wrap :deep(.vt-col-actions),
.vt-table-wrap :deep(.vt-col-meta) {
    width: 1%;
    white-space: nowrap;
}
.vt-table-wrap :deep(.vt-select-check) {
    opacity: 0;
    transition: opacity 0.15s;
    cursor: pointer;
}
.vt-table-wrap :deep(tr:hover .vt-select-check),
.vt-table-wrap :deep(tr:focus-within .vt-select-check),
.vt-table-wrap.vt-has-selection :deep(.vt-select-check) {
    opacity: 1;
}
.vt-detail-icon {
    width: 3rem;
    height: 3rem;
    background: rgba(99, 102, 241, 0.1);
    color: var(--bs-primary);
    flex-shrink: 0;
}
.vault-secret {
    user-select: all;
    overflow-x: auto;
    white-space: pre-wrap;
    word-break: break-all;
    font-family: var(--bs-font-monospace);
}
</style>
