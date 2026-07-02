<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount, defineAsyncComponent } from 'vue';
import { router, useForm } from '@inertiajs/vue3';
import ShellLayout from '../../Layouts/ShellLayout.vue';
import FolderAccordion from '../../Components/FolderAccordion.vue';
import FileItem from '../../Components/FileItem.vue';
import ItemActions from '../../Components/ItemActions.vue';
import AdvancedSearchModal from '../../Components/AdvancedSearchModal.vue';
import UploadModal from '../../Components/UploadModal.vue';
import ShareModal from '../../Components/ShareModal.vue';
const EditorModal = defineAsyncComponent(() => import('../../Components/EditorModal.vue'));
import RenameModal from '../../Components/RenameModal.vue';
import QuickLookModal from '../../Components/QuickLookModal.vue';
import ContextMenu from '../../Components/ContextMenu.vue';
import { useSelection } from '../../composables/useSelection';
import BatchActions from '../../Components/BatchActions.vue';
import { useJobPolling } from '../../composables/useJobPolling';
import { useQuickLook } from '../../composables/useQuickLook';
import { descendantIds } from '../../lib/folderTree';
import { useStorageMeter } from '../../composables/useStorageMeter';
import EmptyState from '../../Components/EmptyState.vue';
import FilterChips from '../../Components/FilterChips.vue';
import LoadingSkeleton from '../../Components/LoadingSkeleton.vue';
import { usePageLoading } from '../../composables/usePageLoading';
import { useConfirm } from '../../composables/useConfirm';
import { useToast } from '../../composables/useToast';
import { typeLabel } from '../../lib/fileTypes';
import { triggerDownload } from '../../lib/download';
import { fmtBytes, pluralize } from '../../lib/format';
import { TYPE_OPTIONS } from '../../composables/useAdvancedSearch';

// Office formats edited on the full-screen editor page (binary, versioned).
const officeEditTypes = ['docx', 'xlsx', 'xls', 'csv', 'ods'];

const props = defineProps({
    folders: { type: Array, default: () => [] },
    files: { type: Array, default: () => [] },
    current: { type: Object, default: null },
    breadcrumbs: { type: Array, default: () => [] },
    allFolders: { type: Array, default: () => [] },
    allFoldersCapped: { type: Boolean, default: false },
    allTags: { type: Array, default: () => [] },
    searching: { type: Boolean, default: false },
    advanced: { type: Boolean, default: false },
    starredOnly: { type: Boolean, default: false },
    recentOnly: { type: Boolean, default: false },
    flat: { type: Boolean, default: false },
    activeTag: { type: Object, default: null },
    storage: { type: Object, default: () => ({ used: 0, quota: 0 }) },
    maxUploadKb: { type: Number, default: 0 },
    filters: { type: Object, default: () => ({ search: '', sort: 'name', direction: 'asc' }) },
    section: { type: String, default: 'all' },
});


const { pct: storagePct, bars: storageBars } = useStorageMeter(computed(() => props.storage));

const currentId = computed(() => props.current?.id ?? null);

// IDs along the path from root to the current folder — used to auto-expand the tree.
const ancestorIds = computed(() => {
    const byId = Object.fromEntries(props.allFolders.map((f) => [f.id, f]));
    const ids = new Set();
    let id = currentId.value;
    while (id) {
        ids.add(id);
        id = byId[id]?.parent_id ?? null;
    }
    return ids;
});

// ----- Breadcrumb -----
// `href` makes each crumb a real link (VibeBreadcrumb only wires clicks on
// items with an href); we intercept the click for Inertia navigation.
const breadcrumbItems = computed(() => [
    { text: 'Home', folder: null, href: '/', active: !props.current },
    ...props.breadcrumbs.map((b, i) => ({
        text: b.name,
        folder: b.id,
        href: `/?folder=${b.id}`,
        active: i === props.breadcrumbs.length - 1,
    })),
]);

function visitFolder(id) {
    router.get('/', id ? { folder: id } : {}, { preserveScroll: true });
}

// ----- FourPane shell: icon rail | folder tree | contents | detail -----
const SECTIONS = [
    { key: 'all', icon: 'folder-fill', label: 'All' },
    { key: 'recent', icon: 'clock-history', label: 'Recent' },
    { key: 'starred', icon: 'star-fill', label: 'Starred' },
    { key: 'shared', icon: 'people-fill', label: 'Shared' },
    { key: 'trash', icon: 'trash', label: 'Trash' },
];
// Section is driven by the GlobalRail (Home/Recent/Starred/Shared/Trash);
// the page just reflects the server-sent `section` for per-section rendering.
const activeSection = ref(props.section);
const activePane = ref('contents');
const openIds = computed(() => ancestorIds.value);
function selectAccordionFolder(id) { visitFolder(id); activePane.value = 'contents'; }

function onBreadcrumb({ item, event }) {
    event?.preventDefault?.();
    if (!item.active) visitFolder(item.folder);
}

// ----- Search -----
const search = ref(props.filters.search);

function runSearch() {
    router.get('/', { folder: currentId.value, search: search.value }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function clearSearch() {
    search.value = '';
    router.get('/', currentId.value ? { folder: currentId.value } : {}, { preserveScroll: true });
}

function clearAllFilters() {
    router.get('/', {}, { preserveScroll: true });
}

// Human-readable summary of the structured (advanced) filters in play.
const advancedSummary = computed(() => {
    const f = props.filters;
    const parts = [];
    if (f.ftype) parts.push(TYPE_OPTIONS.find((o) => o.value === f.ftype)?.text ?? f.ftype);
    if (f.date_from || f.date_to) {
        const target = f.date_target === 'edited' ? 'Edited' : 'Uploaded';
        parts.push(`${target} ${f.date_from ?? '…'} → ${f.date_to ?? '…'}`);
    }
    if (f.size_min || f.size_max) {
        const lo = f.size_min ? fmtBytes(Number(f.size_min)) : '0';
        const hi = f.size_max ? fmtBytes(Number(f.size_max)) : '∞';
        parts.push(`${lo} – ${hi}`);
    }
    if (f.scope === 'folder') parts.push('this folder');
    return parts.join(' · ');
});

// All active view filters collapsed into one chip bar.
const activeFilters = computed(() => {
    const out = [];
    if (props.filters.search) out.push({ key: 'search', icon: 'search', label: `Search: "${props.filters.search}"`, clear: clearSearch });
    if (props.advanced) out.push({ key: 'advanced', icon: 'sliders', label: advancedSummary.value, clear: clearAllFilters });
    if (props.activeTag) out.push({ key: 'tag', icon: 'tag-fill', label: `Tag: ${props.activeTag.name}`, clear: () => filterByTag(null) });
    if (props.starredOnly) out.push({ key: 'starred', icon: 'star-fill', label: 'Starred', clear: clearAllFilters });
    if (props.recentOnly) out.push({ key: 'recent', icon: 'clock-history', label: 'Recent', clear: clearAllFilters });
    return out;
});

// ----- Unified Finder/Dropbox-style listing -----
// Folders and files share one list; folders sort ahead of files by default.
const items = computed(() => [
    // A folder and a file can share a DB id; `_key` disambiguates sibling keys so
    // Vue's patch reconciliation doesn't collide on duplicate `:key` values.
    ...props.folders.map((f) => ({ ...f, is_dir: true, modified: f.updated_at, _sort: 0, _key: `dir-${f.id}` })),
    ...props.files.map((f) => ({ ...f, is_dir: false, modified: f.created_at, _sort: 1, _key: `file-${f.id}` })),
]);

// Blueprint list column: a compact search box + sort select over a flush
// list-group. Client-side over the loaded folder (folders sort ahead of files).
const contentSearch = ref('');
const contentSort = ref('name');
const contentSortOptions = [
    { value: 'name', text: 'Name' },
    { value: 'modified', text: 'Last modified' },
    { value: 'size', text: 'Size' },
];
const visibleItems = computed(() => {
    const q = contentSearch.value.trim().toLowerCase();
    const list = q ? items.value.filter((i) => i.name?.toLowerCase().includes(q)) : items.value;
    const key = contentSort.value;
    return [...list].sort((a, b) =>
        (a._sort - b._sort)
        || (key === 'size' ? (a.size || 0) - (b.size || 0)
            : key === 'modified' ? String(b.modified ?? '').localeCompare(String(a.modified ?? ''))
                : String(a.name ?? '').localeCompare(String(b.name ?? ''))),
    );
});
const ownedSection = computed(() => ['all', 'recent', 'starred'].includes(activeSection.value));

const versionColumns = [
    { key: 'version', label: 'Version' },
    { key: 'note', label: 'What changed', sortable: false },
    { key: 'size', label: 'Size' },
    { key: 'created_at', label: 'Saved' },
    { key: 'actions', label: '', sortable: false, searchable: false },
];

function toggleStar(item) {
    router.post(`/files/${item.id}/star`, {}, { preserveScroll: true });
}

// Drag-and-drop move: drop a file/folder onto a folder to move it there.
function onDropToFolder({ payload }, folder) {
    if (!payload || !folder?.is_dir || payload.id === folder.id) return;
    router.post(`/files/${payload.id}/move`, { target_id: folder.id }, { preserveScroll: true });
}

// ----- Text / Markdown / HTML editor -----
const markdownTypes = ['md', 'markdown', 'txt', 'text', 'log', 'csv'];
const htmlTypes = ['html', 'htm'];
const editOpen = ref(false);
const editItem = ref(null);
const editKind = ref('markdown');
const editCreating = ref(false);

function isEditable(item) {
    return !item.is_dir && (
        markdownTypes.includes(item.type) ||
        htmlTypes.includes(item.type) ||
        officeEditTypes.includes(item.type)
    );
}

// File menu with Edit hidden for files that can't be edited as text.
// Memoised per item: called multiple times in the row template; rebuilding
// the filtered array on every render is wasted work for large folders.
const _menuCache = new WeakMap();
function fileMenu(item) {
    let m = _menuCache.get(item);
    if (!m) {
        m = fileActions.filter((a) => a.action !== 'edit' || isEditable(item));
        _menuCache.set(item, m);
    }
    return m;
}

async function openEditor(item) {
    if (!isEditable(item)) return;
    // Office docs (docx/xlsx/xls/csv/ods) open in the full-screen editor page.
    if (officeEditTypes.includes(item.type)) {
        router.get(`/files/${item.id}/edit`);
        return;
    }
    editItem.value = item;
    editCreating.value = false;
    editKind.value = htmlTypes.includes(item.type) ? 'html' : 'markdown';
    editOpen.value = true;
}

function openNewMarkdown() {
    editItem.value = null;
    editCreating.value = true;
    editKind.value = 'markdown';
    editOpen.value = true;
}

const newMenu = [
    { text: 'New Folder', action: 'folder', icon: 'folder-plus' },
    { text: 'Markdown file', action: 'markdown', icon: 'markdown' },
    { text: 'Spreadsheet', action: 'xlsx', icon: 'file-earmark-spreadsheet' },
    { text: 'Word document', action: 'docx', icon: 'file-earmark-word' },
    { text: 'Upload files', action: 'upload', icon: 'upload' },
];

function onNewMenu({ item }) {
    if (item.action === 'folder') folderOpen.value = true;
    if (item.action === 'upload') uploadOpen.value = true;
    if (item.action === 'markdown') openNewMarkdown();
    // Blank office docs open the full-screen editor; the file is created on first save.
    if (item.action === 'xlsx' || item.action === 'docx') {
        router.get(`/files/new/${item.action}`, currentId.value ? { folder: currentId.value } : {});
    }
}

// ----- Multi-select + batch operations (Finder-style) -----
function openItem(item) {
    if (item.is_dir) {
        visitFolder(item.id);
    } else {
        quickLook(item);
    }
}

const {
    selectMode, selectedIds, selectedItems,
    isSelected, toggleSel, clearSelection, onItemClick,
} = useSelection(items, openItem);

const { confirm } = useConfirm();
const toast = useToast();

// Right-click context menu over a file/folder row or card.
const ctxOpen = ref(false);
const ctxPos = ref({ x: 0, y: 0 });
const ctxItem = ref(null);
const ctxMenu = computed(() => {
    if (!ctxItem.value) return [];
    return ctxItem.value.is_dir ? folderActions : fileMenu(ctxItem.value);
});
function openContext({ item, event }) {
    ctxItem.value = item;
    ctxPos.value = { x: event.clientX, y: event.clientY };
    ctxOpen.value = true;
}
function onContextSelect(action) {
    if (ctxItem.value) onAction(ctxItem.value, { item: action });
}

// ----- Detail pane (Task 4): editor/preview + trash + shared-aware actions -----
const selectedDetail = ref(null);
function selectContentItem(item) {
    // Trash rows always open the detail pane — even folders can't be browsed
    // into (they're soft-deleted), only restored or purged.
    if (activeSection.value === 'trash') {
        selectedDetail.value = item;
        activePane.value = 'detail';
        return;
    }
    // Shared folders don't belong to the current user's tree; browse them via
    // the dedicated shared-folder route instead of the owner's `/` listing.
    if (activeSection.value === 'shared' && item.is_dir) {
        router.get(`/shared/${item.id}`, {}, { preserveScroll: true });
        return;
    }
    if (item.is_dir) {
        visitFolder(item.id);
        return;
    }
    selectedDetail.value = item;
    activePane.value = 'detail';
}
function restoreFromTrash(item) {
    router.post(`/trash/${item.id}/restore`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            if (selectedDetail.value?.id === item.id) selectedDetail.value = null;
            toast.push('Restored');
        },
    });
}
async function purgeFromTrash(item) {
    if (!await confirm({
        title: 'Delete forever',
        message: `Permanently delete "${item.name}"? This cannot be undone.`,
        confirmLabel: 'Delete forever',
        variant: 'danger',
    })) return;
    router.delete(`/trash/${item.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            if (selectedDetail.value?.id === item.id) selectedDetail.value = null;
            toast.push('Permanently deleted', { variant: 'danger' });
        },
    });
}
const detailReadOnly = computed(() => activeSection.value === 'shared'
    && !(selectedDetail.value?.abilities || []).includes('write'));

defineExpose({ selectContentItem });

// List vs thumbnail grid view, remembered across visits. Guarded against
// SecurityError thrown by blocked localStorage (e.g. strict privacy mode).
function safeLocalStorage(key, fallback = '') {
    try { return localStorage.getItem(key) ?? fallback; } catch { return fallback; }
}
const viewMode = ref(safeLocalStorage('fm-view') === 'grid' ? 'grid' : 'list');
watch(viewMode, (v) => { try { localStorage.setItem('fm-view', v); } catch { /* blocked */ } });

const { loading } = usePageLoading();

// Grid windowing: render a bounded slice so a 1000-item folder doesn't mount
// 1000 cards at once. "Show more" reveals the next page; reset when items change.
const gridPageSize = 60;
const gridShown = ref(gridPageSize);
const gridItems = computed(() => items.value.slice(0, gridShown.value));
watch(items, () => { gridShown.value = gridPageSize; });

// List-view page: persist in URL so refresh returns to the same page.
const listPage = ref(Number(new URLSearchParams(window.location.search).get('list_page') || '1'));
watch([() => props.current, () => props.filters?.search], () => { listPage.value = 1; });
watch(listPage, (p) => {
    const url = new URL(window.location.href);
    if (p > 1) url.searchParams.set('list_page', String(p));
    else url.searchParams.delete('list_page');
    window.history.replaceState({}, '', url.toString());
});


const fileActions = [
    { text: 'Download', action: 'download', icon: 'download' },
    { text: 'Edit', action: 'edit', icon: 'pencil-square' },
    { text: 'Details', action: 'details', icon: 'info-circle' },
    { text: 'Share', action: 'share', icon: 'person-plus' },
    { text: 'Tags', action: 'tags', icon: 'tags' },
    { text: 'Versions', action: 'versions', icon: 'clock-history' },
    { text: 'Rename', action: 'rename', icon: 'pencil' },
    { text: 'Move', action: 'move', icon: 'arrows-move' },
    { text: 'Copy', action: 'copy', icon: 'files' },
    { divider: true },
    { text: 'Delete', action: 'delete', icon: 'trash' },
];

const folderActions = [
    { text: 'Share', action: 'share', icon: 'person-plus' },
    { text: 'Tags', action: 'tags', icon: 'tags' },
    { text: 'Rename', action: 'rename', icon: 'pencil' },
    { text: 'Move', action: 'move', icon: 'arrows-move' },
    { text: 'Copy', action: 'copy', icon: 'files' },
    { divider: true },
    { text: 'Delete', action: 'delete', icon: 'trash' },
];

function onAction(item, { item: action }) {
    if (action.action === 'download') triggerDownload(`/download/${item.id}`);
    if (action.action === 'edit') openEditor(item);
    if (action.action === 'details') openDetails(item);
    if (action.action === 'share') openShare(item);
    if (action.action === 'tags') openTags(item);
    if (action.action === 'versions') openVersions(item);
    if (action.action === 'rename') openRename(item);
    if (action.action === 'move') openTransfer(item, 'move');
    if (action.action === 'copy') openTransfer(item, 'copy');
    if (action.action === 'delete') destroy(item);
}

// Run a file action from inside Quick Look: close the preview first so the
// action's own modal isn't stacked on top of it.
function onQuickAction({ item: action }) {
    const target = quickFile.value;
    if (!target) return;
    quickOpen.value = false;
    onAction(target, { item: action });
}

// ----- Versions -----
const versionsOpen = ref(false);
const versionsItem = ref(null);

function openVersions(item) {
    versionsItem.value = item;
    versionsOpen.value = true;
}

async function restoreVersion(version) {
    if (!await confirm({ title: 'Restore version', message: `Restore version ${version.version}? Current content is kept in history.`, confirmLabel: 'Restore' })) return;
    router.post(`/files/${versionsItem.value.id}/versions/${version.id}/restore`, {}, {
        preserveScroll: true,
        onSuccess: () => {
            versionsOpen.value = false;
        },
    });
}

// ----- Details -----
const detailsOpen = ref(false);
const detailsItem = ref(null);

const metadataLabels = {
    width: 'Width',
    height: 'Height',
    camera_make: 'Camera Make',
    camera_model: 'Camera Model',
    taken_at: 'Taken At',
    orientation: 'Orientation',
    title: 'Title',
    artist: 'Artist',
    album: 'Album',
    duration: 'Duration (s)',
    bitrate: 'Bitrate (kbps)',
    sample_rate: 'Sample Rate',
    channels: 'Channels',
    preview: 'Preview',
};

const detailsRows = computed(() => {
    const item = detailsItem.value;
    if (!item) return [];
    const rows = [
        { label: 'Type', value: item.mime || 'folder' },
        { label: 'Size', value: fmtBytes(item.size) },
        { label: 'Uploaded', value: item.created_at },
    ];
    if (item.hash) rows.push({ label: 'SHA-256', value: item.hash });
    for (const [key, val] of Object.entries(item.metadata ?? {})) {
        rows.push({ label: metadataLabels[key] ?? key, value: String(val) });
    }
    return rows;
});

function openDetails(item) {
    detailsItem.value = item;
    detailsOpen.value = true;
}

// ----- Quick Look -----
const quickFiles = computed(() => props.files);
const {
    quickOpen, quickIndex, quickFile, selectedIndex,
    open: quickLook, openAtSelected: quickOpenSelected,
    step: quickStep, close: quickClose,
} = useQuickLook(quickFiles);

// Neighbours for the Quick Look hover thumbnail previews.
const quickPrev = computed(() => {
    const n = props.files.length;
    return n ? props.files[(quickIndex.value - 1 + n) % n] : null;
});
const quickNext = computed(() => {
    const n = props.files.length;
    return n ? props.files[(quickIndex.value + 1) % n] : null;
});

function onKey(e) {
    // Cmd/Ctrl-K focuses the search box from anywhere.
    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
        e.preventDefault();
        document.getElementById('global-search')?.focus();
        return;
    }

    const tag = (e.target?.tagName || '').toLowerCase();
    const inField = ['input', 'textarea', 'select'].includes(tag) || !!e.target?.isContentEditable;

    if (quickOpen.value) {
        if (!inField) {
            if (e.key === 'ArrowRight' || e.key === 'ArrowDown') { e.preventDefault(); quickStep(1); }
            if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') { e.preventDefault(); quickStep(-1); }
            if (e.key === ' ') { e.preventDefault(); quickClose(); }
        }
        if (e.key === 'Escape') quickClose();
        return;
    }

    if (!inField && e.key === ' ' && props.files.length) {
        e.preventDefault();
        quickOpenSelected();
    }
}

// ----- Upload -----
const uploadOpen = ref(false);

// ----- Create folder -----
const folderOpen = ref(false);
const folderForm = useForm({ folder_name: '', parent_id: currentId.value });

function submitFolder() {
    folderForm.parent_id = currentId.value;
    folderForm.post('/folders', {
        onSuccess: () => {
            folderForm.reset();
            folderOpen.value = false;
        },
    });
}

// ----- Rename -----
const renameOpen = ref(false);
const renameTarget = ref(null);

function openRename(item) {
    renameTarget.value = item;
    renameOpen.value = true;
}

// ----- Move / Copy -----
const transferOpen = ref(false);
const transferMode = ref('move');
const transferItem = ref(null);
const transferForm = useForm({ target_id: null });

const destinationOptions = computed(() => {
    const item = transferItem.value;
    // Folders the item cannot be moved/copied into (itself + its descendants).
    const excluded = item?.is_dir ? descendantIds(item.id, props.allFolders) : new Set();
    const options = [{ value: null, text: 'Home' }];
    for (const f of props.allFolders) {
        if (!excluded.has(f.id)) options.push({ value: f.id, text: f.name });
    }
    return options;
});

function openTransfer(item, mode) {
    transferItem.value = { ...item };
    transferMode.value = mode;
    transferForm.clearErrors();
    transferForm.target_id = null;
    transferOpen.value = true;
}

function submitTransfer() {
    const url = `/files/${transferItem.value.id}/${transferMode.value}`;
    transferForm.post(url, {
        preserveScroll: true,
        onSuccess: () => {
            transferOpen.value = false;
        },
    });
}

// ----- Share / permissions -----
const shareOpen = ref(false);
const shareTarget = ref(null);

function openShare(item) {
    shareTarget.value = item;
    shareOpen.value = true;
}

// ----- Tags -----
const tagsOpen = ref(false);
const tagsItem = ref(null);
const tagList = ref([]);
const tagInput = ref('');
const tagSaving = ref(false);

const tagSuggestions = computed(() =>
    props.allTags.map((t) => t.name).filter((n) => !tagList.value.includes(n))
);

function openTags(item) {
    tagsItem.value = item;
    tagList.value = (item.tags ?? []).map((t) => t.name);
    tagInput.value = '';
    tagsOpen.value = true;
}

const MAX_TAGS = 50;
const MAX_TAG_LEN = 40;
const flashedTag = ref('');
function addTag(name) {
    const value = (name ?? tagInput.value).trim().slice(0, MAX_TAG_LEN);
    if (value && !tagList.value.includes(value) && tagList.value.length < MAX_TAGS) {
        tagList.value.push(value);
        // Briefly flash the new badge so the add is visually confirmed.
        flashedTag.value = value;
        setTimeout(() => { if (flashedTag.value === value) flashedTag.value = ''; }, 600);
    }
    tagInput.value = '';
}

function removeTag(name) {
    tagList.value = tagList.value.filter((n) => n !== name);
}

function saveTags() {
    tagSaving.value = true;
    router.put(`/files/${tagsItem.value.id}/tags`, { tags: tagList.value }, {
        preserveScroll: true,
        onSuccess: () => {
            tagsOpen.value = false;
        },
        onFinish: () => {
            tagSaving.value = false;
        },
    });
}

function filterByTag(id) {
    router.get('/', { tag: id }, { preserveScroll: true });
}

// ----- Delete -----
// ----- Advanced search + smart folders (modal owns the form) -----
const advOpen = ref(false);

async function destroy(item) {
    const msg = item.is_dir && item.item_count > 0
        ? `Delete folder "${item.name}" and its ${pluralize(item.item_count, 'item')}? Everything inside moves to trash.`
        : `Move "${item.name}" to trash?`;
    if (!await confirm({ title: 'Move to trash', message: msg, confirmLabel: 'Move to trash', variant: 'danger' })) return;
    router.delete(`/delete/${item.id}`, {
        preserveScroll: true,
        onSuccess: () => toast.push(`"${item.name}" moved to trash`, {
            undo: () => router.post(`/trash/${item.id}/restore`, {}, { preserveScroll: true }),
        }),
    });
}

// ----- Background-job status polling -----
const filesRef = computed(() => props.files);
const { start: startPolling } = useJobPolling(filesRef, () =>
    router.reload({ only: ['files'], preserveScroll: true })
);

onMounted(() => {
    // Open a file/folder directly when navigated from the sidebar tree (?open=id).
    const openId = new URLSearchParams(window.location.search).get('open');
    if (openId) {
        const f = props.files.find((x) => String(x.id) === openId);
        if (f) {
            quickLook(f);
        } else {
            const dir = props.folders.find((x) => String(x.id) === openId);
            if (dir) visitFolder(dir.id);
        }
    }
    startPolling();
    window.addEventListener('keydown', onKey);
});
onBeforeUnmount(() => {
    window.removeEventListener('keydown', onKey);
});
</script>

<template>
    <ShellLayout v-model:active-pane="activePane">
        <template #viewNav>
            <FolderAccordion
                v-if="activeSection === 'all'"
                :folders="allFolders"
                :selected-id="currentId"
                :open-ids="openIds"
                @select-folder="selectAccordionFolder"
                @new-folder="() => folderOpen = true"
            />
            <div v-else class="side-heading">
                <VibeIcon :icon="SECTIONS.find((s) => s.key === activeSection)?.icon || 'folder'" />
                {{ SECTIONS.find((s) => s.key === activeSection)?.label }}
            </div>

            <template v-if="allTags.length">
                <div class="side-heading"><VibeIcon icon="tags-fill" />Tags</div>
                <div class="d-flex flex-wrap gap-1 px-2">
                    <span
                        v-for="tag in allTags"
                        :key="tag.id"
                        class="badge rounded-pill"
                        :style="{
                            backgroundColor: tag.color || '#6c757d',
                            cursor: 'pointer',
                            opacity: activeTag && activeTag.id === tag.id ? 1 : 0.85,
                        }"
                        @click="filterByTag(tag.id)"
                    >{{ tag.name }}</span>
                </div>
            </template>
        </template>

        <template #topBar>
            <div class="d-flex align-items-center px-3 py-2">
                <VibeBreadcrumb :items="breadcrumbItems" class="mb-0 text-truncate" @item-click="onBreadcrumb">
                    <template #item="{ item, index }">
                        <VibeIcon :icon="index === 0 ? 'house-door-fill' : 'folder-fill'" :class="index === 0 ? '' : 'text-warning'" class="me-1" /><span :title="item.text">{{ item.text }}</span>
                    </template>
                </VibeBreadcrumb>
            </div>
        </template>

        <template #contents>
            <!-- Compact per-view actions -->
            <div class="d-flex align-items-center justify-content-end gap-2 px-3 py-2 border-bottom flex-shrink-0">
                <VibeButton size="sm" variant="primary" title="Upload files" aria-label="Upload" @click="uploadOpen = true">
                    <VibeIcon icon="upload" />
                </VibeButton>
                <VibeDropdown size="sm" variant="secondary" outline :items="newMenu" @item-click="onNewMenu">
                    <template #button><VibeIcon icon="plus-lg" /></template>
                    <template #item="{ item }"><VibeIcon :icon="item.icon" class="me-2" />{{ item.text }}</template>
                </VibeDropdown>
                <VibeButton size="sm" variant="secondary" outline title="Advanced search" aria-label="Advanced search" @click="advOpen = true">
                    <VibeIcon icon="funnel" />
                </VibeButton>
                <VibeButton
                    size="sm"
                    :variant="selectMode ? 'primary' : 'secondary'"
                    :outline="!selectMode"
                    title="Select multiple"
                    aria-label="Select multiple"
                    @click="selectMode ? clearSelection() : (selectMode = true)"
                >
                    <VibeIcon icon="check2-square" />
                </VibeButton>
                <VibeButton
                    size="sm"
                    :variant="viewMode === 'grid' ? 'primary' : 'secondary'"
                    outline
                    :title="viewMode === 'grid' ? 'List view' : 'Thumbnail view'"
                    aria-label="Toggle view"
                    @click="viewMode = viewMode === 'grid' ? 'list' : 'grid'"
                >
                    <VibeIcon :icon="viewMode === 'grid' ? 'list-ul' : 'grid-3x3-gap-fill'" />
                </VibeButton>
            </div>

            <!-- Blueprint header: search this column + sort -->
            <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom flex-shrink-0">
                <VibeFormInput v-model="contentSearch" type="search" placeholder="Search this folder…" no-wrapper size="sm" class="flex-grow-1" />
                <VibeFormSelect v-model="contentSort" :options="contentSortOptions" no-wrapper size="sm" style="width: auto" aria-label="Sort by" />
            </div>

            <!-- Batch action bar + modals -->
            <BatchActions
                :selected-items="selectedItems"
                :current-id="currentId"
                :destination-options="destinationOptions"
                @done="clearSelection"
                @cleared="clearSelection"
            />

            <!-- Single compact chip bar for all active view filters. -->
            <FilterChips :chips="activeFilters" @clear-all="clearAllFilters" />

            <LoadingSkeleton v-if="loading" :rows="6" :cols="4" />

            <!-- Thumbnail / grid view (windowed: render a slice, reveal more on demand) -->
            <template v-if="!loading && viewMode === 'grid'">
                <VibeRow class="g-3">
                    <VibeCol v-for="item in gridItems" :key="item._key" :xs="6" :sm="4" :md="3" :xl="2">
                        <FileItem
                            :item="item"
                            view="grid"
                            :select-mode="selectMode"
                            :selected="isSelected(item.id)"
                            :menu="item.is_dir ? folderActions : fileMenu(item)"
                            @open="onItemClick"
                            @toggle-select="toggleSel"
                            @star="toggleStar"
                            @action="onAction(item, $event)"
                            @drop="onDropToFolder"
                            @context="openContext"
                        />
                    </VibeCol>
                    <VibeCol v-if="!items.length" :cols="12">
                        <EmptyState
                            :icon="flat ? 'search' : 'folder2-open'"
                            :title="flat ? 'No matching files' : 'This folder is empty'"
                            :hint="flat ? 'Try a different search or filter.' : 'Upload files or create a folder to get started.'"
                        />
                    </VibeCol>
                </VibeRow>
                <div v-if="gridShown < items.length" class="text-center my-3">
                    <VibeButton variant="secondary" outline @click="gridShown += gridPageSize">
                        Show more ({{ items.length - gridShown }} more)
                    </VibeButton>
                </div>
            </template>

            <!-- Blueprint list view: flush list-group of folders + files. -->
            <ul v-else-if="!loading" class="list-group list-group-flush overflow-auto flex-grow-1 mb-0">
                <li
                    v-for="item in visibleItems"
                    :key="item._key"
                    class="fm-row list-group-item list-group-item-action d-flex align-items-center gap-2 px-3 py-2"
                    :class="{ active: !item.is_dir && selectedDetail && selectedDetail.id === item.id }"
                    @contextmenu.prevent="openContext({ item, event: $event })"
                >
                    <div class="flex-grow-1 min-w-0">
                        <FileItem
                            :item="item"
                            view="list"
                            :select-mode="selectMode"
                            :selected="isSelected(item.id)"
                            @open="() => selectContentItem(item)"
                            @toggle-select="toggleSel"
                            @drop="onDropToFolder"
                            @tag="filterByTag"
                            @context="openContext"
                        />
                        <div class="fm-meta small text-muted text-truncate">
                            {{ item.modified }} · {{ item.is_dir ? pluralize(item.item_count, 'item') : fmtBytes(item.size) }}
                        </div>
                    </div>
                    <ItemActions
                        v-if="ownedSection"
                        :item="item"
                        :menu="item.is_dir ? folderActions : fileMenu(item)"
                        class="flex-shrink-0"
                        @click.stop
                        @star="toggleStar"
                        @action="onAction(item, $event)"
                    />
                </li>
                <li v-if="!visibleItems.length" class="list-group-item border-0 py-5">
                    <EmptyState
                        :icon="flat ? 'search' : 'folder2-open'"
                        :title="flat ? 'No matching files' : 'This folder is empty'"
                        :hint="flat ? 'Try a different search or filter.' : 'Upload files or create a folder to get started.'"
                    />
                </li>
            </ul>
        </template>

        <template #detail>
            <div v-if="activeSection === 'trash' && selectedDetail" class="p-3">
                <h6 class="text-truncate mb-3">{{ selectedDetail.name }}</h6>
                <div class="d-flex gap-2">
                    <VibeButton variant="primary" @click="restoreFromTrash(selectedDetail)">
                        <VibeIcon icon="arrow-counterclockwise" class="me-1" />Restore
                    </VibeButton>
                    <VibeButton variant="danger" outline @click="purgeFromTrash(selectedDetail)">
                        <VibeIcon icon="trash3" class="me-1" />Delete forever
                    </VibeButton>
                </div>
            </div>
            <div v-else-if="selectedDetail" class="p-3">
                <h6 class="text-truncate mb-2">{{ selectedDetail.name }}</h6>
                <p class="text-muted small mb-3">{{ typeLabel(selectedDetail) }} · {{ fmtBytes(selectedDetail.size) }}</p>
                <p v-if="detailReadOnly" class="text-muted small">
                    <VibeIcon icon="lock-fill" class="me-1" />Read-only
                </p>
                <div class="d-flex gap-2">
                    <VibeButton v-if="isEditable(selectedDetail) && !detailReadOnly" variant="primary" @click="openEditor(selectedDetail)">
                        <VibeIcon icon="pencil-square" class="me-1" />Edit
                    </VibeButton>
                    <VibeButton variant="secondary" outline @click="quickLook(selectedDetail)">
                        <VibeIcon icon="eye" class="me-1" />{{ isEditable(selectedDetail) ? 'Preview' : 'Open' }}
                    </VibeButton>
                </div>
            </div>
            <EmptyState v-else icon="folder2-open" title="Select a file" />
        </template>
    </ShellLayout>

    <!-- Editor modal -->
    <EditorModal v-model="editOpen" :item="editItem" :creating="editCreating" :kind="editKind" :parent-id="currentId" />

    <!-- Details modal -->
    <VibeModal v-model="detailsOpen" :title="detailsItem?.name || 'Details'" centered fullscreen hide-footer>
        <dl class="row mb-0">
            <template v-for="row in detailsRows" :key="row.label">
                <dt class="col-4 text-nowrap text-muted small">{{ row.label }}</dt>
                <dd class="col-8 text-break font-monospace small mb-2">{{ row.value }}</dd>
            </template>
        </dl>
    </VibeModal>

    <!-- Share modal -->
    <ShareModal v-model="shareOpen" :item="shareTarget" />

    <!-- Tags modal -->
    <VibeModal v-model="tagsOpen" :title="`Tags — ${tagsItem?.name || ''}`" centered>
        <div class="d-flex flex-wrap gap-2 mb-3">
            <VibeBadge
                v-for="name in tagList"
                :key="name"
                variant="primary"
                class="d-flex align-items-center"
                :class="{ 'tag-flash': name === flashedTag }"
            >
                {{ name }}
                <VibeIcon icon="x" class="ms-1" style="cursor: pointer" @click="removeTag(name)" />
            </VibeBadge>
            <span v-if="!tagList.length" class="text-muted small">No tags yet.</span>
        </div>
        <div class="d-flex gap-2 align-items-end">
            <div class="flex-grow-1">
                <VibeAutocomplete
                    v-model="tagInput"
                    :source="tagSuggestions"
                    label="Add a tag"
                    placeholder="Type a tag and press Add"
                    @select="addTag"
                    @keyup.enter="addTag()"
                />
            </div>
            <VibeButton variant="secondary" @click="addTag()">Add</VibeButton>
        </div>
        <template #footer>
            <VibeButton variant="secondary" outline @click="tagsOpen = false">Cancel</VibeButton>
            <VibeButton variant="primary" :disabled="tagSaving" @click="saveTags"><VibeSpinner v-if="tagSaving" size="sm" class="me-1" />{{ tagSaving ? 'Saving…' : 'Save' }}</VibeButton>
        </template>
    </VibeModal>

    <!-- Versions modal -->
    <VibeModal v-model="versionsOpen" :title="`Versions — ${versionsItem?.name || ''}`" centered fullscreen hide-footer>
        <p class="text-muted small">
            Current: version {{ versionsItem?.version }}.
            <span v-if="!versionsItem?.versions?.length">No previous versions.</span>
        </p>
        <VibeDataTable
            v-if="versionsItem?.versions?.length"
            :items="versionsItem.versions"
            :columns="versionColumns"
            row-key="_key"
            hover
            :searchable="false"
            :show-per-page="false"
            :per-page="100"
            :responsive="true"
        >
            <template #cell(version)="{ item }">v{{ item.version }}</template>
            <template #cell(note)="{ item }">
                <span v-if="item.note" class="small">{{ item.note }}</span>
                <span v-else class="text-muted fst-italic small">—</span>
            </template>
            <template #cell(size)="{ item }">{{ fmtBytes(item.size) }}</template>
            <template #cell(created_at)="{ item }"><span class="small">{{ item.created_at }}</span></template>
            <template #cell(actions)="{ item }">
                <div class="d-flex justify-content-end gap-1">
                    <VibeButton
                        variant="success"
                        size="sm"
                        :href="`/files/${versionsItem.id}/versions/${item.id}/download`"
                        :aria-label="`Download version ${item.version}`"
                    >
                        <VibeIcon icon="download" />
                    </VibeButton>
                    <VibeButton variant="primary" size="sm" outline @click="restoreVersion(item)">
                        <VibeIcon icon="arrow-counterclockwise" class="me-1" />Restore
                    </VibeButton>
                </div>
            </template>
        </VibeDataTable>
    </VibeModal>

    <!-- Quick Look modal -->
    <QuickLookModal
        v-model="quickOpen"
        :file="quickFile"
        :index="quickIndex"
        :total="files.length"
        :menu="quickFile ? fileMenu(quickFile) : []"
        :prev-file="quickPrev"
        :next-file="quickNext"
        @step="quickStep"
        @action="onQuickAction"
    />

    <!-- Upload modal -->
    <UploadModal v-model="uploadOpen" :parent-id="currentId" :max-upload-kb="maxUploadKb" :storage="storage" />

    <!-- Create folder modal -->
    <VibeModal v-model="folderOpen" title="Create Folder" centered>
        <form @submit.prevent="submitFolder">
            <VibeFormGroup
                label="Folder Name"
                :error="folderForm.errors.folder_name"
                required
            >
                <VibeFormInput v-model="folderForm.folder_name" required />
            </VibeFormGroup>
        </form>
        <template #footer>
            <VibeButton variant="secondary" outline @click="folderOpen = false">Cancel</VibeButton>
            <VibeButton variant="primary" :disabled="folderForm.processing" @click="submitFolder"><VibeSpinner v-if="folderForm.processing" size="sm" class="me-1" />{{ folderForm.processing ? 'Creating…' : 'Create' }}</VibeButton>
        </template>
    </VibeModal>

    <!-- Rename modal -->
    <RenameModal v-model="renameOpen" :item="renameTarget" />

    <!-- Move / Copy modal -->
    <VibeModal v-model="transferOpen" :title="transferMode === 'move' ? 'Move To' : 'Copy To'" centered>
        <form @submit.prevent="submitTransfer">
            <VibeFormGroup
                label="Destination Folder"
                :error="transferForm.errors.target_id"
            >
                <VibeFormSelect v-model="transferForm.target_id" :options="destinationOptions" />
                <small v-if="allFoldersCapped" class="text-muted d-block mt-1">Showing first 2,000 folders — use search to find others.</small>
            </VibeFormGroup>
        </form>
        <template #footer>
            <VibeButton variant="secondary" outline @click="transferOpen = false">Cancel</VibeButton>
            <VibeButton variant="primary" :disabled="transferForm.processing" @click="submitTransfer">
                <VibeSpinner v-if="transferForm.processing" size="sm" class="me-1" />{{ transferForm.processing ? (transferMode === 'move' ? 'Moving…' : 'Copying…') : (transferMode === 'move' ? 'Move' : 'Copy') }}
            </VibeButton>
        </template>
    </VibeModal>

    <!-- Advanced search -->
    <AdvancedSearchModal
        v-model="advOpen"
        :filters="filters"
        :all-tags="allTags"
        :current-folder-id="currentId"
        :current-folder-name="current?.name ?? null"
    />

    <!-- Right-click context menu -->
    <ContextMenu
        v-model="ctxOpen"
        :x="ctxPos.x"
        :y="ctxPos.y"
        :items="ctxMenu"
        @select="onContextSelect"
    />
</template>

<style scoped>
.select-check {
    cursor: pointer;
}
.min-w-0 {
    min-width: 0;
}
.fm-row {
    cursor: pointer;
    border-left: 0;
    border-right: 0;
}
.fm-row.active {
    background: rgba(99, 102, 241, 0.12);
}
.fm-meta {
    padding-left: 2.9rem;
    margin-top: -0.2rem;
}
/* Brief confirmation pulse when a tag is added. */
.tag-flash {
    animation: tag-flash 0.6s ease-out;
}
@keyframes tag-flash {
    0% { box-shadow: 0 0 0 0 rgba(13, 110, 253, 0.6); transform: scale(1.12); }
    100% { box-shadow: 0 0 0 8px rgba(13, 110, 253, 0); transform: scale(1); }
}
</style>
