<script setup>
import { ref, computed, onMounted } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import { BookmarkStatus } from '../../lib/constants';
import ShellLayout from '../../Layouts/ShellLayout.vue';
import FolderAccordion from '../../Components/FolderAccordion.vue';
import SelectBar from '../../Components/SelectBar.vue';
import { useConfirm, usePrompt } from '../../composables/useConfirm';
import { useToast } from '../../composables/useToast';
import { useSelection } from '../../composables/useSelection';
import { useCategoryFolders } from '../../composables/useCategoryFolders';
import { useEscapeToClear } from '../../composables/useEscapeToClear';
import ResourceModal from '../../Components/ResourceModal.vue';
import EmptyState from '../../Components/EmptyState.vue';
import LoadingSkeleton from '../../Components/LoadingSkeleton.vue';
import { usePageLoading } from '../../composables/usePageLoading';

const props = defineProps({
    bookmarks: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    screenshotsEnabled: { type: Boolean, default: false },
});

const { confirm } = useConfirm();
const toast = useToast();
const { prompt } = usePrompt();
const { loading } = usePageLoading();

// ----- Shell panes -----
const activePane = ref('contents');

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


// Categories are flat strings; useCategoryFolders adapts them to
// FolderAccordion rows rooted at the "Bookmarks" node.
const folderNames = computed(() => [...folders.value, ...(counts.value['General'] ? ['General'] : [])]);
const {
    folders: accordionFolders,
    counts: accordionCounts,
    selectedId: selectedFolderId,
    pickById: pickFolderById,
    openIds: accordionOpenIds,
} = useCategoryFolders({
    rootName: 'Bookmarks',
    rootIcon: 'bookmark-fill',
    names: folderNames,
    counts,
    total: computed(() => props.bookmarks.length),
    selected: selectedFolder,
    onPick: (name) => pickFolder(name),
});

function pickFolder(f) {
    selectedFolder.value = f;
    clearContentSelection();
    activePane.value = 'contents'; // mobile: reveal the list after picking a folder/feed
}

const listed = computed(() => {
    if (selectedFolder.value === null) return props.bookmarks;
    return props.bookmarks.filter((b) => (b.category || 'General') === selectedFolder.value);
});

// ----- Explorer table -----
// VibeDataTable owns search + sort + pagination over the loaded set.
const tableColumns = [
    { key: '_select', label: '', sortable: false, searchable: false, headerClass: 'st-col-select', class: 'st-col-select' },
    { key: 'title', label: 'Title', class: 'bm-col-title' },
    { key: 'url', label: 'URL', headerClass: 'd-none d-lg-table-cell', class: 'd-none d-lg-table-cell st-col-meta' },
    { key: 'category', label: 'Folder', headerClass: 'd-none d-xl-table-cell', class: 'd-none d-xl-table-cell st-col-meta' },
    { key: 'click_count', label: 'Opens', class: 'st-col-meta' },
    { key: '_actions', label: '', sortable: false, searchable: false, headerClass: 'st-col-actions', class: 'st-col-actions' },
];
const tableItems = computed(() => listed.value.map((b) => ({ ...b, category: b.category || 'General' })));
const listPage = ref(1);

// ----- Breadcrumb -----
const breadcrumbItems = computed(() => [
    { text: 'Bookmarks', folder: null, href: '/bookmarks', active: selectedFolder.value === null },
    ...(selectedFolder.value !== null ? [{
        text: selectedFolder.value,
        folder: selectedFolder.value,
        href: '/bookmarks',
        active: true,
    }] : []),
]);
function onBreadcrumb({ item, event }) {
    event?.preventDefault?.();
    if (!item.active) pickFolder(item.folder);
}

// ----- Selection / detail -----
const selectedId = ref(null);
const selectedBookmark = computed(() => props.bookmarks.find((b) => b.id === selectedId.value) || null);

function selectBookmark(id) {
    selectedId.value = id;
    activePane.value = 'detail';
}

// ----- Multi-select (hover checkbox column) -----
const bookmarksRef = computed(() => props.bookmarks);
const { selectedIds: bmSelectedIds, selectedItems: selectedBms, isSelected: bmIsSelected, toggleSel: bmToggle, clearSelection: bmClearSel } = useSelection(bookmarksRef, (b) => selectBookmark(b.id));

function clearContentSelection() {
    bmClearSel();
    selectedId.value = null;
    if (activePane.value === 'detail') activePane.value = 'contents';
}

useEscapeToClear(clearContentSelection);

async function bulkDeleteBms() {
    if (!await confirm({ title: `Delete ${selectedBms.value.length} bookmarks`, message: 'Delete the selected bookmarks? This cannot be undone.', confirmLabel: 'Delete', variant: 'danger' })) return;
    for (const b of selectedBms.value) {
        router.delete(`/bookmarks/${b.id}`, { preserveScroll: true });
    }
    toast.push(`${selectedBms.value.length} bookmark(s) removed`, { variant: 'danger' });
    clearContentSelection();
}

// ----- Favicon fallback -----
function prettyHost(url) {
    try {
        return new URL(url).host.replace(/^www\./, '');
    } catch {
        return url;
    }
}

const failedIcons = ref(new Set());
function onIconError(id) {
    const next = new Set(failedIcons.value);
    next.add(id);
    failedIcons.value = next;
}

// ----- Add / edit -----
const bmModal = ref(null);
const form = useForm({ title: '', url: '', description: '', icon: '', category: '', shared: false });

function openAdd() {
    const prefill = selectedFolder.value && selectedFolder.value !== 'General'
        ? { category: selectedFolder.value }
        : {};
    bmModal.value?.openAdd(prefill);
}

// Quick Actions "Save Bookmark" deep-links here with ?new=1.
onMounted(() => {
    if (new URLSearchParams(window.location.search).get('new')) openAdd();
});

function openEdit(b) {
    Object.assign(form, {
        title: b.title, url: b.url, description: b.description || '',
        icon: b.icon_name || '', category: b.category || '', shared: b.shared,
    });
    bmModal.value?.openEdit(b);
}

async function remove(b) {
    if (!await confirm({ title: 'Delete bookmark', message: `Delete “${b.title}”? This cannot be undone.`, confirmLabel: 'Delete', variant: 'danger' })) return;
    router.delete(`/bookmarks/${b.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            if (selectedId.value === b.id) clearContentSelection();
            toast.push(`”${b.title}” removed`, { variant: 'danger' });
        },
    });
}

function toggleStar(b) {
    router.post(`/bookmarks/${b.id}/star`, {}, { preserveScroll: true, preserveState: true });
}

const subscribing = ref(false);
function subscribeFeed(b) {
    subscribing.value = true;
    router.post(`/bookmarks/${b.id}/subscribe`, {}, {
        preserveScroll: true,
        onSuccess: () => toast.push(`Subscribed to “${b.title}”`, { variant: 'success' }),
        onFinish: () => { subscribing.value = false; },
    });
}

const rowMenu = [
    { text: 'Edit', action: 'edit', icon: 'pencil' },
    { divider: true },
    { text: 'Delete', action: 'delete', icon: 'trash' },
];
function onRowMenu(b, { item }) {
    if (item.action === 'edit') openEdit(b);
    if (item.action === 'delete') remove(b);
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

const deadCount = computed(() => props.bookmarks.filter((b) => b.status === BookmarkStatus.DEAD).length);
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
        && !await confirm({ title: 'Confirm', message: maintenanceConfirms[action], confirmLabel: 'Delete', variant: 'danger' })) {
        return;
    }
    router.post(`/bookmarks/${action}`, {}, { preserveScroll: true, preserveState: true });
}
</script>

<template>
    <ShellLayout v-model:active-pane="activePane" :detail-visible="!!selectedBookmark">
        <template #viewNav>
            <FolderAccordion
                :folders="accordionFolders"
                :selected-id="selectedFolderId"
                :open-ids="accordionOpenIds"
                :counts="accordionCounts"
                @select-folder="pickFolderById"
                @new-folder="addFolder"
            />
        </template>

        <!-- Breadcrumb + actions share the top-bar line: one solid-primary CTA
             (Add bookmark); icon-only utilities are quiet light ghosts. -->
        <template #topBar>
            <div class="d-flex align-items-center gap-2 p-2">
                <VibeBreadcrumb :items="breadcrumbItems" class="breadcrumb mb-0 pb-0 text-truncate min-w-0" @item-click="onBreadcrumb">
                    <template #item="{ item, index }">
                        <VibeIcon :icon="index === 0 ? 'bookmark-fill' : 'folder2'" class="me-1" /><span :title="item.text">{{ item.text }}</span>
                    </template>
                </VibeBreadcrumb>
                <div class="d-flex align-items-center gap-2 ms-auto flex-shrink-0">
                    <VibeButton size="sm" variant="primary" title="New bookmark" aria-label="New bookmark" @click="openAdd">
                        <VibeIcon icon="plus-lg" />
                    </VibeButton>
                    <VibeButton size="sm" variant="light" title="Import a Chrome/Firefox bookmarks HTML export" aria-label="Import bookmarks" @click="importInput?.click()">
                        <VibeIcon icon="upload" />
                    </VibeButton>
                    <VibeDropdown size="sm" variant="light" menu-end title="Maintenance" :items="maintenanceItems" @item-click="runMaintenance($event.item.action)">
                        <template #button><VibeIcon icon="three-dots-vertical" /></template>
                        <template #item="{ item }"><VibeIcon :icon="item.icon" class="me-2" />{{ item.text }}</template>
                    </VibeDropdown>
                    <input ref="importInput" type="file" accept=".html,.htm,text/html" class="d-none" @change="onImportFile">
                </div>
            </div>
        </template>

        <template #contents>
            <SelectBar :count="selectedBms.length" class="mx-2 mt-2" @clear="bmClearSel">
                <VibeButton variant="danger" size="sm" outline @click="bulkDeleteBms">
                    <VibeIcon icon="trash" class="me-1" />Delete
                </VibeButton>
            </SelectBar>

            <LoadingSkeleton v-if="loading" :rows="8" :cols="3" />

            <div
                v-else
                class="st-table-wrap overflow-auto flex-grow-1 px-2 pt-1"
                :class="{ 'st-has-selection': bmSelectedIds.size > 0 }"
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
                    search-placeholder="Search bookmarks…"
                    :show-per-page="false"
                    :per-page="50"
                    v-model:current-page="listPage"
                    sort-by="title"
                    class="bm-table"
                    @row-clicked="(b) => selectBookmark(b.id)"
                >
                    <template #cell(_select)="{ item }">
                        <VibeFormCheckbox
                            class="st-select-check"
                            :model-value="bmIsSelected(item.id)"
                            :aria-label="bmIsSelected(item.id) ? `Deselect ${item.title}` : `Select ${item.title}`"
                            @update:model-value="bmToggle(item.id)"
                            @click.stop
                        />
                    </template>
                    <template #cell(title)="{ item }">
                        <div class="d-flex align-items-center min-w-0">
                            <img v-if="item.icon_url && !failedIcons.has(item.id)" :src="item.icon_url" alt="" width="18" height="18" class="me-2 flex-shrink-0" @error="onIconError(item.id)">
                            <VibeIcon v-else :icon="item.icon_name || 'link-45deg'" class="me-2 text-muted flex-shrink-0" />
                            <span class="text-truncate" :title="item.title">{{ item.title }}</span>
                            <VibeIcon v-if="item.starred" icon="star-fill" class="text-warning small ms-2 flex-shrink-0" title="Starred" />
                            <VibeIcon v-if="item.feed_url" icon="rss-fill" class="text-warning small ms-2 flex-shrink-0" title="Has an RSS feed" />
                            <VibeIcon v-if="item.status === BookmarkStatus.DEAD" icon="exclamation-triangle-fill" class="text-danger ms-2 flex-shrink-0" title="Link unreachable" />
                            <VibeIcon v-if="item.shared" icon="people-fill" class="text-muted small ms-2 flex-shrink-0" title="Shared" />
                        </div>
                    </template>
                    <template #cell(url)="{ item }">
                        <span class="text-muted small text-truncate d-inline-block bm-url" :title="item.url">{{ item.url }}</span>
                    </template>
                    <template #cell(category)="{ item }">
                        <span class="text-muted small text-nowrap">{{ item.category }}</span>
                    </template>
                    <template #cell(click_count)="{ item }">
                        <span class="text-muted small text-nowrap">{{ item.click_count }}</span>
                    </template>
                    <template #cell(_actions)="{ item }">
                        <div class="d-flex align-items-center gap-1" @click.stop>
                            <VibeButton
                                v-if="item.can_edit"
                                variant="link"
                                class="p-0 me-2"
                                :aria-label="item.starred ? 'Unstar' : 'Star'"
                                @click="toggleStar(item)"
                            >
                                <VibeIcon :icon="item.starred ? 'star-fill' : 'star'" :class="item.starred ? 'text-warning' : 'text-muted'" />
                            </VibeButton>
                            <VibeDropdown v-if="item.can_edit" size="sm" variant="light" menu-end title="Bookmark actions" :items="rowMenu" @item-click="onRowMenu(item, $event)">
                                <template #button><VibeIcon icon="three-dots-vertical" /><span class="visually-hidden">Bookmark actions</span></template>
                                <template #item="{ item: a }"><VibeIcon :icon="a.icon" class="me-2" />{{ a.text }}</template>
                            </VibeDropdown>
                        </div>
                    </template>
                </VibeDataTable>
                <EmptyState v-else icon="bookmark-star" title="No bookmarks here" hint="Add a link to get started." />
            </div>
        </template>

        <template #detail>
            <div v-if="selectedBookmark" class="d-flex flex-column h-100 p-3 overflow-auto">
                <div class="d-flex align-items-center gap-3 mb-3 flex-shrink-0">
                    <span class="bm-detail-icon d-inline-flex align-items-center justify-content-center rounded">
                        <img v-if="selectedBookmark.icon_url && !failedIcons.has(selectedBookmark.id)" :src="selectedBookmark.icon_url" alt="" width="32" height="32" @error="onIconError(selectedBookmark.id)">
                        <VibeIcon v-else :icon="selectedBookmark.icon_name || 'link-45deg'" class="fs-3" />
                    </span>
                    <div class="min-w-0">
                        <div class="h5 mb-0 text-break">{{ selectedBookmark.title }}</div>
                        <div class="small text-muted">
                            {{ selectedBookmark.category || 'General' }} · {{ selectedBookmark.click_count }} opens
                            <VibeBadge v-if="selectedBookmark.shared" class="text-bg-light ms-1"><VibeIcon icon="people-fill" class="me-1" />Shared</VibeBadge>
                        </div>
                    </div>
                </div>

                <VibeAlert v-if="selectedBookmark.status === BookmarkStatus.DEAD" variant="warning" class="py-2 flex-shrink-0">
                    <VibeIcon icon="exclamation-triangle-fill" class="me-1" />This link did not respond on the last check.
                </VibeAlert>

                <a :href="`/bookmarks/${selectedBookmark.id}/go`" target="_blank" rel="noopener" class="text-break d-block flex-shrink-0">
                    {{ selectedBookmark.url }}
                </a>
                <div v-if="selectedBookmark.feed_url" class="d-flex align-items-center gap-2 mb-3 flex-shrink-0">
                    <a :href="selectedBookmark.feed_url" target="_blank" rel="noopener" class="small text-decoration-none">
                        <VibeIcon icon="rss-fill" class="text-warning me-1" />RSS feed
                    </a>
                    <VibeBadge v-if="selectedBookmark.feed_subscribed" variant="success" pill>Subscribed</VibeBadge>
                    <VibeButton v-else variant="warning" size="sm" :disabled="subscribing" @click="subscribeFeed(selectedBookmark)">
                        <VibeIcon icon="rss" class="me-1" />Subscribe
                    </VibeButton>
                </div>
                <div v-else class="mb-3"></div>
                <p v-if="selectedBookmark.description" class="text-body flex-shrink-0">{{ selectedBookmark.description }}</p>

                <a v-if="selectedBookmark.screenshot_url" :href="`/bookmarks/${selectedBookmark.id}/go`" target="_blank" rel="noopener" class="d-block bm-shot-wrap flex-grow-1 min-h-0 mb-3">
                    <img :src="selectedBookmark.screenshot_url" alt="Site preview" class="bm-shot rounded border">
                </a>
                <!-- No self-hosted screenshot: show an intentional placeholder
                     (favicon + host) instead of a blank gap. -->
                <a v-else :href="`/bookmarks/${selectedBookmark.id}/go`" target="_blank" rel="noopener" class="d-flex flex-column align-items-center justify-content-center bm-shot-empty flex-grow-1 min-h-0 mb-3 rounded border text-muted">
                    <img v-if="selectedBookmark.icon_url && !failedIcons.has(selectedBookmark.id)" :src="selectedBookmark.icon_url" alt="" width="40" height="40" class="mb-2" @error="onIconError(selectedBookmark.id)">
                    <VibeIcon v-else icon="globe2" class="display-6 mb-2" />
                    <span class="small text-truncate px-3" style="max-width: 100%">{{ prettyHost(selectedBookmark.url) }}</span>
                    <span class="small">No preview</span>
                </a>

                <div class="d-flex gap-2 mt-auto flex-shrink-0">
                    <VibeButton :href="`/bookmarks/${selectedBookmark.id}/go`" target="_blank" rel="noopener" variant="primary">
                        <VibeIcon icon="box-arrow-up-right" class="me-1" />Open
                    </VibeButton>
                    <template v-if="selectedBookmark.can_edit">
                        <VibeButton variant="secondary" outline :title="selectedBookmark.starred ? 'Unstar' : 'Star'" :aria-label="selectedBookmark.starred ? 'Unstar' : 'Star'" @click="toggleStar(selectedBookmark)">
                            <VibeIcon :icon="selectedBookmark.starred ? 'star-fill' : 'star'" :class="{ 'text-warning': selectedBookmark.starred }" />
                        </VibeButton>
                        <VibeButton variant="secondary" outline @click="openEdit(selectedBookmark)"><VibeIcon icon="pencil" class="me-1" />Edit</VibeButton>
                        <VibeButton variant="danger" outline @click="remove(selectedBookmark)"><VibeIcon icon="trash" class="me-1" />Delete</VibeButton>
                    </template>
                </div>
            </div>
        </template>
    </ShellLayout>

    <ResourceModal
        ref="bmModal"
        :form="form"
        store-url="/bookmarks"
        :update-url="(id) => `/bookmarks/${id}`"
        add-title="Add bookmark"
        edit-title="Edit bookmark"
    >
        <VibeFormGroup label="Title" :error="form.errors.title">
            <VibeFormInput v-model="form.title" placeholder="Payroll portal" />
        </VibeFormGroup>
        <VibeFormGroup label="URL" :error="form.errors.url">
            <VibeFormInput v-model="form.url" placeholder="https://…" />
        </VibeFormGroup>
        <VibeFormGroup label="Description" :error="form.errors.description">
            <VibeFormInput v-model="form.description" placeholder="Optional" maxlength="500" />
        </VibeFormGroup>
        <div class="row">
            <div class="col-6">
                <VibeFormGroup label="Folder" :error="form.errors.category" help-text="Type a name to create a folder">
                    <VibeAutocomplete v-model="form.category" :source="folders" placeholder="e.g. Tools" />
                </VibeFormGroup>
            </div>
            <div class="col-6">
                <VibeFormGroup label="Icon" :error="form.errors.icon" help-text="Bootstrap icon name (blank = auto favicon)">
                    <VibeFormInput v-model="form.icon" placeholder="link-45deg" />
                </VibeFormGroup>
            </div>
        </div>
        <VibeFormCheckbox v-model="form.shared" label="Share with everyone" />
    </ResourceModal>
</template>

<style scoped>.min-h-0 {
    min-height: 0;
}
.bm-url {
    max-width: 22rem;
    vertical-align: middle;
}
.bm-detail-icon {
    width: 3rem;
    height: 3rem;
    background: rgba(99, 102, 241, 0.1);
    color: var(--bs-primary);
    flex-shrink: 0;
}
.bm-shot-wrap {
    min-height: 0;
}
.bm-shot {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: top;
}
.bm-shot-empty {
    min-height: 8rem;
    background: var(--bs-tertiary-bg);
    text-decoration: none;
    transition: background 0.1s;
}
.bm-shot-empty:hover {
    background: var(--bs-secondary-bg);
    color: var(--bs-body-color);
}
</style>
