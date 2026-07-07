<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount, defineAsyncComponent } from 'vue';
import { router, useForm, usePage } from '@inertiajs/vue3';
import ShellLayout from '../../Layouts/ShellLayout.vue';
import FolderAccordion from '../../Components/FolderAccordion.vue';
import FileItem from '../../Components/FileItem.vue';
import FilePreview from '../../Components/FilePreview.vue';
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
import RssItemRow from '../../Components/Rss/RssItemRow.vue';
import { usePageLoading } from '../../composables/usePageLoading';
import { useConfirm } from '../../composables/useConfirm';
import { useToast } from '../../composables/useToast';
import { typeLabel } from '../../lib/fileTypes';
import { triggerDownload } from '../../lib/download';
import { isTextInputTarget } from '../../lib/dom';
import { fmtBytes, pluralize } from '../../lib/format';
import { TYPE_OPTIONS } from '../../composables/useAdvancedSearch';

// Office formats edited on the full-screen editor page (binary, versioned).
const officeEditTypes = ['docx', 'xlsx', 'xls', 'csv', 'ods'];

const props = defineProps({
    folders: { type: Array, default: () => [] },
    files: { type: Array, default: () => [] },
    rssItems: { type: Array, default: () => [] },
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

// The tree roots at "Home" (id 0 — real folder ids start at 1); every
// top-level folder becomes its child so the parent is always in the list.
const HOME_ID = 0;
const accordionFolders = computed(() => [
    { id: HOME_ID, name: 'Home', parent_id: null, icon: 'house-door-fill' },
    ...props.allFolders.map((f) => ({ ...f, parent_id: f.parent_id ?? HOME_ID })),
]);

// IDs along the path from root to the current folder — used to auto-expand the tree.
const ancestorIds = computed(() => {
    const byId = Object.fromEntries(props.allFolders.map((f) => [f.id, f]));
    const ids = new Set([HOME_ID]);
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
// Section pages root the trail at their own name (Recent, Starred, …).
const SECTION_ROOTS = {
    all: { text: 'Home', icon: 'house-door-fill', href: '/' },
    recent: { text: 'Recent', icon: 'clock-history', href: '/recent' },
    starred: { text: 'Starred', icon: 'star-fill', href: '/starred' },
    shared: { text: 'Shared', icon: 'people-fill', href: '/shared' },
    trash: { text: 'Trash', icon: 'trash-fill', href: '/trash' },
};
const sectionRoot = computed(() => SECTION_ROOTS[activeSection.value] ?? SECTION_ROOTS.all);
const breadcrumbItems = computed(() => [
    { text: sectionRoot.value.text, folder: null, href: sectionRoot.value.href, active: !props.current },
    ...props.breadcrumbs.map((b, i) => ({
        text: b.name,
        folder: b.id,
        href: `/?folder=${b.id}`,
        active: i === props.breadcrumbs.length - 1,
    })),
]);

// Folder navigation is a PARTIAL reload: only the folder-view props change, so
// the sidebar tree (allFolders/allTags/quota) and the FolderAccordion instance
// are preserved instead of being torn down and rebuilt on every click (which
// read as a "reload then refresh" flicker). preserveState keeps the accordion's
// expansion state; openIds still recomputes to auto-reveal the new path.
const FOLDER_VIEW_PROPS = [
    'folders', 'files', 'current', 'breadcrumbs', 'rssItems',
    'searching', 'advanced', 'flat', 'activeTag', 'section',
    'starredOnly', 'recentOnly', 'filters',
];
function visitFolder(id) {
    router.get('/', id ? { folder: id } : {}, {
        only: FOLDER_VIEW_PROPS,
        preserveState: true,
        preserveScroll: true,
    });
}

// ----- FourPane shell: icon rail | folder tree | contents | detail -----
// Section is driven by the GlobalRail (Home/Recent/Starred/Shared/Trash);
// the page just reflects the server-sent `section` for per-section rendering.
// Folder-less sections drop the folders column entirely (folders-visible).
const activeSection = ref(props.section);
const activePane = ref('contents');
const openIds = computed(() => ancestorIds.value);
function selectAccordionFolder(id) { visitFolder(id === HOME_ID ? null : id); activePane.value = 'contents'; }

function onBreadcrumb({ item, event }) {
    event?.preventDefault?.();
    if (!item.active) visitFolder(item.folder);
}

// ----- Search -----
const search = ref(props.filters.search);
// Folder navigation is a preserveState partial reload, so the component isn't
// remounted — keep the search box in sync with the server's filter (e.g. it
// clears when you navigate into a folder from a search result).
watch(() => props.filters.search, (v) => {
    if ((v ?? '') !== (search.value ?? '')) search.value = v ?? '';
});

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

const ownedSection = computed(() => ['all', 'recent', 'starred'].includes(activeSection.value));

// ----- Explorer table (list view) -----
// VibeDataTable owns sorting + pagination; the navbar owns search. Rows carry
// a `kind` label for the Kind column and folders get size -1 so they sort
// ahead of the smallest file on an ascending size sort.
const tableItems = computed(() => items.value.map((item) => ({
    ...item,
    size: item.is_dir ? -1 : (item.size ?? 0),
    kind: item.is_dir ? 'Folder' : typeLabel(item),
})));

const tableColumns = computed(() => [
    ...(ownedSection.value ? [{ key: '_select', label: '', sortable: false, searchable: false, headerClass: 'st-col-select', class: 'st-col-select' }] : []),
    { key: 'name', label: 'Name', class: 'fm-col-name' },
    { key: 'modified', label: 'Modified', headerClass: 'd-none d-lg-table-cell', class: 'd-none d-lg-table-cell st-col-meta' },
    { key: 'size', label: 'Size', class: 'st-col-meta' },
    { key: 'kind', label: 'Kind', headerClass: 'd-none d-xl-table-cell', class: 'd-none d-xl-table-cell st-col-meta' },
    ...(ownedSection.value ? [{ key: '_actions', label: '', sortable: false, searchable: false, headerClass: 'st-col-actions', class: 'st-col-actions' }] : []),
]);

const listSortBy = ref(props.filters?.sort === 'modified' || props.filters?.sort === 'size' ? props.filters.sort : 'name');
const listSortDesc = ref(props.filters?.direction === 'desc');
const listPerPage = ref(50);

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

// ----- Multi-select + batch operations (Drive-style) -----
// A row click selects (file) or navigates (folder); the hover checkbox column
// drives multi-select. The composable's click-modifier path is unused here.
const {
    selectedIds, selectedItems,
    isSelected, toggleSel, clearSelection,
} = useSelection(items, selectContentItem);

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

// The detail column only exists while a row is selected; clearing the
// selection (Esc, click on empty space, navigation) collapses it.
const detailVisible = computed(() => !!selectedDetail.value);

function clearContentSelection() {
    clearSelection();
    selectedDetail.value = null;
    if (activePane.value === 'detail') activePane.value = 'contents';
}

// Navigating to another folder replaces the listing; drop any selection that
// referenced the old one so the preview pane never shows a stale ghost.
watch(() => props.current?.id ?? null, () => {
    clearContentSelection();
});

function onRowClick(item) {
    selectContentItem(item);
}

defineExpose({ selectContentItem });

// List vs thumbnail grid view, remembered across visits. Guarded against
// SecurityError thrown by blocked localStorage (e.g. strict privacy mode).
function safeLocalStorage(key, fallback = '') {
    try { return localStorage.getItem(key) ?? fallback; } catch { return fallback; }
}
const viewMode = ref(safeLocalStorage('fm-view') === 'grid' ? 'grid' : 'list');
watch(viewMode, (v) => { try { localStorage.setItem('fm-view', v); } catch { /* blocked */ } });

// Thumbnail size (grid view only), remembered across visits. Each size maps
// to responsive row-cols counts on the grid's VibeRow.
const GRID_ROW_COLS = {
    s: { rowCols: 4, rowColsMd: 5, rowColsXl: 6 },
    m: { rowCols: 3, rowColsMd: 4, rowColsXl: 5 },
    l: { rowCols: 2, rowColsMd: 3, rowColsXl: 4 },
};
const GRID_SIZES = [
    { value: 's', label: 'S', title: 'Small thumbnails' },
    { value: 'm', label: 'M', title: 'Medium thumbnails' },
    { value: 'l', label: 'L', title: 'Large thumbnails' },
];
const gridSize = ref(['s', 'm', 'l'].includes(safeLocalStorage('fm-grid-size')) ? safeLocalStorage('fm-grid-size') : 'm');
watch(gridSize, (v) => { try { localStorage.setItem('fm-grid-size', v); } catch { /* blocked */ } });
const gridRowCols = computed(() => GRID_ROW_COLS[gridSize.value] ?? GRID_ROW_COLS.m);

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

    if (!isTextInputTarget(e) && e.key === 'Escape') {
        clearContentSelection();
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

// ----- Smart Folders (saved searches, shared via Inertia middleware) -----
const savedSearches = computed(() => usePage().props.savedSearches ?? []);
function runSavedSearch(s) {
    router.get('/', s.params || {});
}
async function deleteSavedSearch(s) {
    if (await confirm({ title: 'Remove smart folder', message: `Remove smart folder "${s.name}"?`, confirmLabel: 'Remove', variant: 'danger' })) {
        router.delete(`/saved-searches/${s.id}`, { preserveScroll: true });
    }
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
    <ShellLayout v-model:active-pane="activePane" :detail-visible="detailVisible" :folders-visible="activeSection === 'all'">
        <template #viewNav>
            <FolderAccordion
                v-if="activeSection === 'all'"
                :folders="accordionFolders"
                :selected-id="currentId ?? HOME_ID"
                :open-ids="openIds"
                @select-folder="selectAccordionFolder"
                @new-folder="() => folderOpen = true"
            />

            <template v-if="savedSearches.length">
                <div class="side-heading"><VibeIcon icon="funnel-fill" />Smart Folders</div>
                <div
                    v-for="s in savedSearches"
                    :key="s.id"
                    class="side-row d-flex align-items-center saved-search px-2 py-1 rounded"
                    role="button"
                    @click="runSavedSearch(s)"
                >
                    <VibeIcon icon="bookmark-star-fill" class="me-2 text-primary" />
                    <span class="text-truncate flex-grow-1">{{ s.name }}</span>
                    <!-- Real button: click listeners on a bare VibeIcon never fire. -->
                    <button
                        type="button"
                        class="del-btn border-0 bg-transparent p-0 text-muted"
                        :aria-label="`Remove smart folder ${s.name}`"
                        title="Remove"
                        @click.stop="deleteSavedSearch(s)"
                    ><VibeIcon icon="x" /></button>
                </div>
            </template>

            <template v-if="allTags.length">
                <div class="side-heading"><VibeIcon icon="tags-fill" />Tags</div>
                <div class="d-flex flex-wrap gap-1 px-1">
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

        <!-- Breadcrumb + folder actions share one top-bar line: crumbs left,
             actions right. Button system: one solid-primary CTA per region
             (Upload); every icon-only utility is a quiet light ghost. -->
        <template #topBar>
            <div class="d-flex align-items-center gap-2 p-2">
                <VibeBreadcrumb :items="breadcrumbItems" class="breadcrumb mb-0 pb-0 text-truncate min-w-0" @item-click="onBreadcrumb">
                    <template #item="{ item, index }">
                        <VibeIcon :icon="index === 0 ? sectionRoot.icon : 'folder2'" class="me-1" /><span :title="item.text">{{ item.text }}</span>
                    </template>
                </VibeBreadcrumb>
                <div class="d-flex align-items-center gap-2 ms-auto flex-shrink-0">
                    <VibeButtonGroup v-if="viewMode === 'grid'" size="sm" aria-label="Thumbnail size">
                        <VibeButton
                            v-for="s in GRID_SIZES"
                            :key="s.value"
                            size="sm"
                            :variant="gridSize === s.value ? 'primary' : 'light'"
                            :title="s.title"
                            :aria-pressed="gridSize === s.value"
                            @click="gridSize = s.value"
                        >{{ s.label }}</VibeButton>
                    </VibeButtonGroup>
                    <VibeButton size="sm" variant="primary" title="Upload files" aria-label="Upload" @click="uploadOpen = true">
                        <VibeIcon icon="upload" />
                    </VibeButton>
                    <VibeDropdown size="sm" variant="light" menu-end :items="newMenu" @item-click="onNewMenu">
                        <template #button><VibeIcon icon="plus-lg" /></template>
                        <template #item="{ item }"><VibeIcon :icon="item.icon" class="me-2" />{{ item.text }}</template>
                    </VibeDropdown>
                    <VibeButton size="sm" variant="light" title="Advanced search" aria-label="Advanced search" @click="advOpen = true">
                        <VibeIcon icon="funnel" />
                    </VibeButton>
                    <VibeButton
                        size="sm"
                        variant="light"
                        :title="viewMode === 'grid' ? 'List view' : 'Thumbnail view'"
                        aria-label="Toggle view"
                        @click="viewMode = viewMode === 'grid' ? 'list' : 'grid'"
                    >
                        <VibeIcon :icon="viewMode === 'grid' ? 'list-ul' : 'grid-3x3-gap-fill'" />
                    </VibeButton>
                </div>
            </div>
        </template>

        <template #contents>
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

            <!-- Starred RSS items: surfaced on /starred so the "everything I care
                 about" view spans every content type. Click opens the article;
                 the star button toggles starred state via the RSS endpoint. -->
            <div v-if="starredOnly && rssItems.length" class="px-2 pt-2">
                <div class="d-flex align-items-center gap-2 small text-muted text-uppercase fw-semibold mb-1">
                    <VibeIcon icon="rss-fill" class="text-warning" />Starred articles ({{ rssItems.length }})
                </div>
                <RssItemRow
                    v-for="item in rssItems"
                    :key="`rss-${item.id}`"
                    :item="item"
                    :selected="false"
                    @click="router.visit(`/rss/items/${item.id}`)"
                    @toggle-star="router.post(`/rss/items/${item.id}/star`, {}, { preserveScroll: true, preserveState: true })"
                />
                <hr v-if="folders.length || files.length" class="my-3">
            </div>

            <LoadingSkeleton v-if="loading" :rows="6" :cols="4" />

            <!-- Thumbnail / grid view (windowed: render a slice, reveal more on demand) -->
            <div v-if="!loading && viewMode === 'grid'" class="overflow-auto flex-grow-1" @click.self="clearContentSelection">
                <VibeRow
                    v-if="items.length"
                    class="g-2 p-2"
                    :row-cols="gridRowCols.rowCols"
                    :row-cols-md="gridRowCols.rowColsMd"
                    :row-cols-xl="gridRowCols.rowColsXl"
                >
                    <VibeCol v-for="item in gridItems" :key="item._key">
                        <FileItem
                            :item="item"
                            view="grid"
                            :selected="isSelected(item.id)"
                            :menu="item.is_dir ? folderActions : fileMenu(item)"
                            @open="selectContentItem"
                            @toggle-select="toggleSel"
                            @star="toggleStar"
                            @action="onAction(item, $event)"
                            @drop="onDropToFolder"
                            @context="openContext"
                        />
                    </VibeCol>
                </VibeRow>
                <EmptyState
                    v-else
                    :icon="starredOnly && !rssItems.length ? 'star' : (flat ? 'search' : 'folder2-open')"
                    :title="starredOnly && !folders.length && !files.length && !rssItems.length ? 'Nothing starred yet' : (flat ? 'No matching files' : 'This folder is empty')"
                    :hint="starredOnly ? 'Star a file, folder, or RSS article to see it here.' : (flat ? 'Try a different search or filter.' : 'Upload files or create a folder to get started.')"
                />
                <div v-if="gridShown < items.length" class="text-center my-3">
                    <VibeButton variant="secondary" outline @click="gridShown += gridPageSize">
                        Show more ({{ items.length - gridShown }} more)
                    </VibeButton>
                </div>
            </div>

            <!-- Explorer table: sortable Name/Modified/Size/Kind columns, hover
                 checkbox multi-select, row click selects (file) or navigates
                 (folder). Search lives in the navbar. -->
            <div
                v-else-if="!loading"
                class="st-table-wrap overflow-auto flex-grow-1 px-2 pt-1"
                :class="{ 'st-has-selection': selectedIds.size > 0 }"
                @click.self="clearContentSelection"
            >
                <VibeDataTable
                    v-if="items.length"
                    :items="tableItems"
                    :columns="tableColumns"
                    row-key="_key"
                    hover
                    small
                    clickable
                    :searchable="false"
                    :show-per-page="false"
                    :per-page="listPerPage"
                    v-model:current-page="listPage"
                    v-model:sort-by="listSortBy"
                    v-model:sort-desc="listSortDesc"
                    class="fm-table"
                    @row-clicked="onRowClick"
                >
                    <template #cell(_select)="{ item }">
                        <VibeFormCheckbox
                            class="st-select-check"
                            :model-value="isSelected(item.id)"
                            :aria-label="isSelected(item.id) ? `Deselect ${item.name}` : `Select ${item.name}`"
                            @update:model-value="toggleSel(item.id)"
                            @click.stop
                        />
                    </template>
                    <template #cell(name)="{ item }">
                        <FileItem
                            :item="item"
                            view="list"
                            @drop="onDropToFolder"
                            @tag="filterByTag"
                            @context="openContext"
                        />
                    </template>
                    <template #cell(modified)="{ item }">
                        <span class="text-muted small text-nowrap">{{ item.modified }}</span>
                    </template>
                    <template #cell(size)="{ item }">
                        <span class="text-muted small text-nowrap">
                            {{ item.is_dir ? pluralize(item.item_count, 'item') : fmtBytes(item.size) }}
                        </span>
                    </template>
                    <template #cell(kind)="{ item }">
                        <span class="text-muted small text-nowrap">{{ item.kind }}</span>
                    </template>
                    <template #cell(_actions)="{ item }">
                        <ItemActions
                            :item="item"
                            :menu="item.is_dir ? folderActions : fileMenu(item)"
                            @click.stop
                            @star="toggleStar"
                            @action="onAction(item, $event)"
                        />
                    </template>
                </VibeDataTable>
                <EmptyState
                    v-else
                    :icon="starredOnly && !rssItems.length ? 'star' : (flat ? 'search' : 'folder2-open')"
                    :title="starredOnly && !folders.length && !files.length && !rssItems.length ? 'Nothing starred yet' : (flat ? 'No matching files' : 'This folder is empty')"
                    :hint="starredOnly ? 'Star a file, folder, or RSS article to see it here.' : (flat ? 'Try a different search or filter.' : 'Upload files or create a folder to get started.')"
                />
            </div>
        </template>

        <template #detail>
            <div v-if="activeSection === 'trash' && selectedDetail" class="p-2">
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
            <div v-else-if="selectedDetail" class="d-flex flex-column h-100 p-2">
                <!-- Type-aware preview (image / pdf / markdown / office / …)
                     fills every pixel the info block below doesn't need. -->
                <FilePreview :file="selectedDetail" class="flex-grow-1 min-h-0 mb-3" />

                <h6 class="text-break mb-2 flex-shrink-0">{{ selectedDetail.name }}</h6>

                <!-- Info -->
                <dl class="row small mb-3 flex-shrink-0">
                    <dt class="col-4 text-muted fw-normal">Type</dt>
                    <dd class="col-8 text-break mb-1">{{ typeLabel(selectedDetail) }}</dd>
                    <dt class="col-4 text-muted fw-normal">Size</dt>
                    <dd class="col-8 mb-1">{{ fmtBytes(selectedDetail.size) }}</dd>
                    <dt class="col-4 text-muted fw-normal">Modified</dt>
                    <dd class="col-8 mb-0">{{ selectedDetail.modified }}</dd>
                </dl>

                <p v-if="detailReadOnly" class="text-muted small flex-shrink-0"><VibeIcon icon="lock-fill" class="me-1" />Read-only</p>

                <!-- Actions -->
                <div class="d-flex flex-wrap gap-2 flex-shrink-0">
                    <VibeButton variant="primary" @click="quickLook(selectedDetail)">
                        <VibeIcon icon="eye" class="me-1" />{{ isEditable(selectedDetail) ? 'Preview' : 'Open' }}
                    </VibeButton>
                    <VibeButton v-if="isEditable(selectedDetail) && !detailReadOnly" variant="secondary" outline @click="openEditor(selectedDetail)">
                        <VibeIcon icon="pencil-square" class="me-1" />Edit
                    </VibeButton>
                    <VibeButton variant="secondary" outline @click="openShare(selectedDetail)">
                        <VibeIcon icon="person-plus" class="me-1" />Share
                    </VibeButton>
                    <VibeButton variant="secondary" outline @click="triggerDownload(`/download/${selectedDetail.id}`)">
                        <VibeIcon icon="download" class="me-1" />Download
                    </VibeButton>
                </div>
            </div>
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
.min-h-0 {
    min-height: 0;
}
/* Breadcrumb flattening now lives globally in theme.css so every page matches. */
/* Brief confirmation pulse when a tag is added. */
.tag-flash {
    animation: tag-flash 0.6s ease-out;
}
@keyframes tag-flash {
    0% { box-shadow: 0 0 0 0 rgba(13, 110, 253, 0.6); transform: scale(1.12); }
    100% { box-shadow: 0 0 0 8px rgba(13, 110, 253, 0); transform: scale(1); }
}
</style>
