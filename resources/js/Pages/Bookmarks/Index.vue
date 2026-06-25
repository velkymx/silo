<script setup>
import { ref, computed, watch, onBeforeUnmount } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import ThreePane from '../../Components/ThreePane.vue';
import { useConfirm, usePrompt } from '../../composables/useConfirm';
import { useToast } from '../../composables/useToast';
import AppModal from '../../Components/AppModal.vue';
import AppFormGroup from '../../Components/AppFormGroup.vue';

const props = defineProps({
    bookmarks: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    screenshotsEnabled: { type: Boolean, default: false },
});

const { confirm } = useConfirm();
const toast = useToast();
const { prompt } = usePrompt();

// ----- Server-side Scout search (debounced) -----
const search = ref(props.filters?.search ?? '');
let searchTimer = null;
watch(search, (v) => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        router.get('/bookmarks', { search: v || undefined },
            { preserveState: true, preserveScroll: true, replace: true });
    }, 250);
});
onBeforeUnmount(() => clearTimeout(searchTimer));

// ----- Folders (categories) -----
const selectedFolder = ref(null); // null = All
const folders = computed(() =>
    [...new Set(props.bookmarks.map((b) => b.category).filter(Boolean))].sort()
);
const counts = computed(() => {
    const map = {};
    for (const b of props.bookmarks) {
        const key = b.category || 'General';
        map[key] = (map[key] ?? 0) + 1;
    }
    return map;
});

// ----- Sort -----
const sortOrder = ref('name-asc');
const sortOptions = [
    { value: 'name-asc', text: 'Name A–Z', icon: 'sort-alpha-down' },
    { value: 'name-desc', text: 'Name Z–A', icon: 'sort-alpha-up' },
    { value: 'opens', text: 'Most opened', icon: 'graph-up' },
];

const feedCount = computed(() => props.bookmarks.filter((b) => b.feed_url).length);

const listed = computed(() => {
    let list;
    if (selectedFolder.value === null) list = props.bookmarks;
    else if (selectedFolder.value === '__feeds__') list = props.bookmarks.filter((b) => b.feed_url);
    else list = props.bookmarks.filter((b) => (b.category || 'General') === selectedFolder.value);
    list = [...list];
    if (sortOrder.value === 'name-asc') list.sort((a, b) => a.title.localeCompare(b.title));
    else if (sortOrder.value === 'name-desc') list.sort((a, b) => b.title.localeCompare(a.title));
    else if (sortOrder.value === 'opens') list.sort((a, b) => b.click_count - a.click_count);
    return list;
});

// ----- Selection / detail -----
const selectedId = ref(null);
const selectedBookmark = computed(() => props.bookmarks.find((b) => b.id === selectedId.value) || null);

// ----- Favicon fallback -----
const failedIcons = ref(new Set());
function onIconError(id) {
    const next = new Set(failedIcons.value);
    next.add(id);
    failedIcons.value = next;
}

// ----- Add / edit -----
const showModal = ref(false);
const editingId = ref(null);
const form = useForm({ title: '', url: '', description: '', icon: '', color: '', category: '', shared: false });

function openAdd() {
    editingId.value = null;
    form.reset();
    form.clearErrors();
    form.category = selectedFolder.value && selectedFolder.value !== 'General' ? selectedFolder.value : '';
    showModal.value = true;
}

function openEdit(b) {
    editingId.value = b.id;
    form.clearErrors();
    Object.assign(form, {
        title: b.title, url: b.url, description: b.description || '',
        icon: b.icon_name || '', color: b.color || '', category: b.category || '', shared: b.shared,
    });
    showModal.value = true;
}

function save() {
    const opts = { preserveScroll: true, preserveState: true, onSuccess: () => { showModal.value = false; } };
    if (editingId.value) form.put(`/bookmarks/${editingId.value}`, opts);
    else form.post('/bookmarks', opts);
}

async function remove(b) {
    if (!await confirm({ title: 'Remove bookmark', message: `Remove “${b.title}”?`, confirmLabel: 'Remove', variant: 'danger' })) return;
    router.delete(`/bookmarks/${b.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            if (selectedId.value === b.id) selectedId.value = null;
            toast.push(`”${b.title}” removed`, { variant: 'danger' });
        },
    });
}

function toggleStar(b) {
    router.post(`/bookmarks/${b.id}/star`, {}, { preserveScroll: true, preserveState: true });
}

async function addFolder() {
    const name = await prompt({ title: 'New folder', message: 'Folder name:', confirmLabel: 'Create' });
    if (!name || !name.trim()) return;
    selectedFolder.value = name.trim();
    openAdd();
}

// ----- Import + maintenance -----
const importInput = ref(null);
function onImportFile(e) {
    const file = e.target.files?.[0];
    if (!file) return;
    router.post('/bookmarks/import', { file }, { forceFormData: true, preserveScroll: true });
    e.target.value = '';
}

const deadCount = computed(() => props.bookmarks.filter((b) => b.status === 'dead').length);
const maintenanceItems = computed(() => [
    { text: 'Re-check all links', action: 'validate', icon: 'arrow-repeat' },
    { text: 'Hydrate missing', action: 'hydrate', icon: 'magic' },
    { text: 'Remove duplicates', action: 'dedup', icon: 'files' },
    { text: deadCount.value ? `Remove ${deadCount.value} dead link(s)` : 'Remove dead links', action: 'prune', icon: 'trash' },
]);
const maintenanceConfirms = {
    dedup: 'Delete duplicate URLs, keeping the earliest of each?',
    prune: 'Delete every bookmark whose last check failed (dead links)?',
};
async function runMaintenance(action) {
    if (maintenanceConfirms[action]
        && !await confirm({ title: 'Confirm', message: maintenanceConfirms[action], confirmLabel: 'Remove', variant: 'danger' })) {
        return;
    }
    router.post(`/bookmarks/${action}`, {}, { preserveScroll: true, preserveState: true });
}
</script>

<template>
    <AppLayout>
        <ThreePane>
            <template #sidebar>
                <div class="d-flex flex-column p-2">
                <div class="d-flex align-items-center justify-content-between px-1 mb-1">
                    <span class="fw-semibold small text-uppercase text-muted">Folders</span>
                    <VibeButton size="sm" variant="light" title="New folder" @click="addFolder">
                        <VibeIcon icon="folder-plus" />
                    </VibeButton>
                </div>
                <button
                    type="button"
                    class="side-row w-100 text-start d-flex align-items-center gap-2 px-2 py-1 rounded border-0 bg-transparent"
                    :class="{ active: selectedFolder === null }"
                    @click="selectedFolder = null"
                >
                    <VibeIcon icon="bookmark-fill" />
                    <span class="flex-grow-1">All Bookmarks</span>
                    <span class="badge text-bg-light">{{ bookmarks.length }}</span>
                </button>
                <button
                    v-if="feedCount"
                    type="button"
                    class="side-row w-100 text-start d-flex align-items-center gap-2 px-2 py-1 rounded border-0 bg-transparent"
                    :class="{ active: selectedFolder === '__feeds__' }"
                    @click="selectedFolder = '__feeds__'"
                >
                    <VibeIcon icon="rss-fill" class="text-warning" />
                    <span class="flex-grow-1">Feeds</span>
                    <span class="badge text-bg-light">{{ feedCount }}</span>
                </button>
                <button
                    v-for="folder in [...folders, ...(counts['General'] ? ['General'] : [])]"
                    :key="folder"
                    type="button"
                    class="side-row w-100 text-start d-flex align-items-center gap-2 px-2 py-1 rounded border-0 bg-transparent"
                    :class="{ active: selectedFolder === folder }"
                    @click="selectedFolder = folder"
                >
                    <VibeIcon :icon="folder === 'General' ? 'folder' : 'folder-fill'" class="text-warning" />
                    <span class="flex-grow-1 text-truncate">{{ folder }}</span>
                    <span class="badge text-bg-light">{{ counts[folder] }}</span>
                </button>
                </div>
            </template>

            <template #list>
                <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom">
                    <VibeFormInput v-model="search" type="search" placeholder="Search…" no-wrapper class="flex-grow-1" />
                    <VibeDropdown size="sm" variant="light" menu-end title="Sort" :items="sortOptions" @item-click="sortOrder = $event.item.value">
                        <template #button><VibeIcon icon="sort-down" /></template>
                        <template #item="{ item }"><VibeIcon :icon="item.icon" class="me-2" />{{ item.text }}</template>
                    </VibeDropdown>
                    <VibeDropdown size="sm" variant="light" menu-end title="Maintenance" :items="maintenanceItems" @item-click="runMaintenance($event.item.action)">
                        <template #button><VibeIcon icon="three-dots-vertical" /></template>
                        <template #item="{ item }"><VibeIcon :icon="item.icon" class="me-2" />{{ item.text }}</template>
                    </VibeDropdown>
                    <VibeButton size="sm" variant="secondary" outline title="Import a Chrome/Firefox bookmarks HTML export" @click="importInput?.click()">
                        <VibeIcon icon="upload" />
                    </VibeButton>
                    <VibeButton size="sm" variant="primary" title="New bookmark" @click="openAdd">
                        <VibeIcon icon="plus-lg" />
                    </VibeButton>
                    <input ref="importInput" type="file" accept=".html,.htm,text/html" class="d-none" @change="onImportFile">
                </div>

                <div class="flex-grow-1 overflow-auto">
                    <p v-if="!listed.length" class="text-muted small px-3 py-4 text-center mb-0">No bookmarks here.</p>
                    <button
                        v-for="b in listed"
                        :key="b.id"
                        type="button"
                        class="bm-row w-100 text-start border-0 border-bottom px-3 py-2 bg-transparent d-flex align-items-center gap-2"
                        :class="{ active: b.id === selectedId }"
                        @click="selectedId = b.id"
                    >
                        <img v-if="b.icon_url && !failedIcons.has(b.id)" :src="b.icon_url" alt="" width="18" height="18" @error="onIconError(b.id)">
                        <VibeIcon v-else :icon="b.icon_name || 'link-45deg'" class="text-muted" />
                        <span class="flex-grow-1" style="min-width: 0">
                            <span class="d-block fw-medium text-truncate">{{ b.title }}</span>
                            <span class="d-block small text-muted text-truncate">{{ b.url }}</span>
                        </span>
                        <VibeIcon v-if="b.starred" icon="star-fill" class="text-warning small" title="Starred" />
                        <VibeIcon v-if="b.feed_url" icon="rss-fill" class="text-warning small" title="Has an RSS feed" />
                        <VibeIcon v-if="b.status === 'dead'" icon="exclamation-triangle-fill" class="text-danger" title="Link unreachable" />
                        <VibeIcon v-if="b.shared" icon="people-fill" class="text-muted small" title="Shared" />
                    </button>
                </div>
            </template>

            <template #detail>
                <template v-if="selectedBookmark">
                    <div class="p-4 overflow-auto">
                        <div class="d-flex align-items-center gap-3 mb-3">
                            <span class="bm-detail-icon d-inline-flex align-items-center justify-content-center rounded">
                                <img v-if="selectedBookmark.icon_url && !failedIcons.has(selectedBookmark.id)" :src="selectedBookmark.icon_url" alt="" width="32" height="32" @error="onIconError(selectedBookmark.id)">
                                <VibeIcon v-else :icon="selectedBookmark.icon_name || 'link-45deg'" class="fs-3" />
                            </span>
                            <div class="min-vw-0">
                                <div class="h5 mb-0 text-break">{{ selectedBookmark.title }}</div>
                                <div class="small text-muted">
                                    {{ selectedBookmark.category || 'General' }} · {{ selectedBookmark.click_count }} opens
                                    <VibeBadge v-if="selectedBookmark.shared" class="text-bg-light ms-1"><VibeIcon icon="people-fill" class="me-1" />Shared</VibeBadge>
                                </div>
                            </div>
                        </div>

                        <VibeAlert v-if="selectedBookmark.status === 'dead'" variant="warning" class="py-2">
                            <VibeIcon icon="exclamation-triangle-fill" class="me-1" />This link did not respond on the last check.
                        </VibeAlert>

                        <a :href="`/bookmarks/${selectedBookmark.id}/go`" target="_blank" rel="noopener" class="text-break d-block">
                            {{ selectedBookmark.url }}
                        </a>
                        <a v-if="selectedBookmark.feed_url" :href="selectedBookmark.feed_url" target="_blank" rel="noopener" class="small text-decoration-none d-inline-block mb-3">
                            <VibeIcon icon="rss-fill" class="text-warning me-1" />RSS feed
                        </a>
                        <div v-else class="mb-3"></div>
                        <p v-if="selectedBookmark.description" class="text-body">{{ selectedBookmark.description }}</p>

                        <a v-if="selectedBookmark.screenshot_url" :href="`/bookmarks/${selectedBookmark.id}/go`" target="_blank" rel="noopener" class="d-block">
                            <img :src="selectedBookmark.screenshot_url" alt="Site preview" class="bm-shot img-fluid rounded border mb-3">
                        </a>

                        <div class="d-flex gap-2 mt-2">
                            <a :href="`/bookmarks/${selectedBookmark.id}/go`" target="_blank" rel="noopener" class="btn btn-primary">
                                <VibeIcon icon="box-arrow-up-right" class="me-1" />Open
                            </a>
                            <template v-if="selectedBookmark.can_edit">
                                <VibeButton variant="secondary" outline :title="selectedBookmark.starred ? 'Unstar' : 'Star'" @click="toggleStar(selectedBookmark)">
                                    <VibeIcon :icon="selectedBookmark.starred ? 'star-fill' : 'star'" :class="{ 'text-warning': selectedBookmark.starred }" />
                                </VibeButton>
                                <VibeButton variant="secondary" outline @click="openEdit(selectedBookmark)"><VibeIcon icon="pencil" class="me-1" />Edit</VibeButton>
                                <VibeButton variant="danger" outline @click="remove(selectedBookmark)"><VibeIcon icon="trash" class="me-1" />Remove</VibeButton>
                            </template>
                        </div>
                    </div>
                </template>
                <div v-else class="d-flex flex-column align-items-center justify-content-center h-100 text-muted">
                    <VibeIcon icon="bookmark" class="fs-1 mb-2" />
                    <p class="mb-0">Select a bookmark.</p>
                </div>
            </template>
        </ThreePane>

        <AppModal v-model="showModal" :title="editingId ? 'Edit bookmark' : 'Add bookmark'">
            <AppFormGroup label="Title" :error="form.errors.title">
                <VibeFormInput v-model="form.title" placeholder="Payroll portal" />
            </AppFormGroup>
            <AppFormGroup label="URL" :error="form.errors.url">
                <VibeFormInput v-model="form.url" placeholder="https://…" />
            </AppFormGroup>
            <AppFormGroup label="Description" :error="form.errors.description">
                <VibeFormInput v-model="form.description" placeholder="Optional" />
            </AppFormGroup>
            <div class="row">
                <div class="col-6">
                    <AppFormGroup label="Folder" :error="form.errors.category" help-text="Type a name to create a folder">
                        <VibeFormInput v-model="form.category" placeholder="e.g. Tools" list="bm-folders" />
                        <datalist id="bm-folders">
                            <option v-for="f in folders" :key="f" :value="f" />
                        </datalist>
                    </AppFormGroup>
                </div>
                <div class="col-6">
                    <AppFormGroup label="Icon" :error="form.errors.icon" help-text="Bootstrap icon name (blank = auto favicon)">
                        <VibeFormInput v-model="form.icon" placeholder="link-45deg" />
                    </AppFormGroup>
                </div>
            </div>
            <VibeFormCheckbox v-model="form.shared">Share with everyone</VibeFormCheckbox>
            <template #footer>
                <VibeButton variant="secondary" outline @click="showModal = false">Cancel</VibeButton>
                <VibeButton variant="primary" :disabled="form.processing" @click="save">
                    {{ editingId ? 'Save' : 'Add' }}
                </VibeButton>
            </template>
        </AppModal>
    </AppLayout>
</template>

<style scoped>
.side-row,
.bm-row {
    cursor: pointer;
    color: var(--bs-body-color);
}
.side-row:hover,
.bm-row:hover {
    background: rgba(99, 102, 241, 0.07) !important;
}
.side-row.active,
.bm-row.active {
    background: rgba(99, 102, 241, 0.12) !important;
    font-weight: 600;
}
.bm-detail-icon {
    width: 3rem;
    height: 3rem;
    background: rgba(99, 102, 241, 0.1);
    color: var(--bs-primary);
    flex-shrink: 0;
}
.bm-shot {
    max-width: 100%;
    max-height: 420px;
    object-fit: cover;
    object-position: top;
}
</style>
