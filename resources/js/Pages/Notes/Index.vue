<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';
import ShellLayout from '../../Layouts/ShellLayout.vue';
import NotesSidebar from '../../Components/Notes/NotesSidebar.vue';
import FolderAccordion from '../../Components/FolderAccordion.vue';
import BacklinksPanel from '../../Components/Notes/BacklinksPanel.vue';
import MarkdownEditor from '../../Components/MarkdownEditor.vue';
import SelectBar from '../../Components/SelectBar.vue';
import EmptyState from '../../Components/EmptyState.vue';
import { getText, http } from '../../lib/http';
import { extractHeadings } from '../../lib/markdownOutline';
import { usePrompt, useConfirm } from '../../composables/useConfirm';
import { useSelection } from '../../composables/useSelection';
import { useToast } from '../../composables/useToast';
import { usePageLoading } from '../../composables/usePageLoading';
import LoadingSkeleton from '../../Components/LoadingSkeleton.vue';
import SaveIndicator from '../../Components/SaveIndicator.vue';

const { prompt } = usePrompt();
const { confirm } = useConfirm();
const toast = useToast();

const props = defineProps({
    rootId: { type: Number, default: null },
    folders: { type: Array, default: () => [] },
    notes: { type: Array, default: () => [] },
    tags: { type: Array, default: () => [] },
    open: { type: Number, default: null },
    createTitle: { type: String, default: null },
});

const selectedId = ref(props.open || null);
const content = ref('');
const saveState = ref('idle'); // 'idle' | 'saving' | 'saved' | 'error'
const activeTag = ref(null);
const selectedFolder = ref(null);
const activePane = ref(props.open ? 'detail' : 'contents');
let suppressSave = false;
let saveTimer = null;
let suppressTimer = null;
let loadSeq = 0;
let unmounted = false;

const { loading } = usePageLoading();
const notesRef = computed(() => props.notes);
const { selectedIds: noteSelectedIds, selectedItems: selectedNotes, isSelected: noteIsSelected, toggleSel: noteToggle, clearSelection: noteClearSel } = useSelection(notesRef, (n) => selectNote(n.id));

async function bulkDeleteNotes() {
    if (!await confirm({ title: `Move ${selectedNotes.value.length} note(s) to trash`, message: 'Move selected notes to trash?', confirmLabel: 'Move to trash', variant: 'danger' })) return;
    for (const n of selectedNotes.value) {
        router.delete(`/notes/${n.id}`, { preserveScroll: true });
    }
    toast.push(`${selectedNotes.value.length} note(s) deleted`);
    clearContentSelection();
}

const filteredNotes = computed(() => {
    let list = props.notes;
    if (activeTag.value !== null) {
        // A parent tag includes its nested children: #work matches #work/projects.
        list = list.filter((n) => n.tags.some(
            (t) => t.name === activeTag.value || t.name.startsWith(activeTag.value + '/'),
        ));
    }
    if (selectedFolder.value !== null) {
        list = list.filter((n) => n.parent_id === selectedFolder.value);
    }
    return list;
});

// ----- Explorer table -----
// VibeDataTable owns search + sort + pagination over the filtered set.
const tableColumns = [
    { key: '_select', label: '', sortable: false, searchable: false, headerClass: 'nt-col-select', class: 'nt-col-select' },
    { key: 'title', label: 'Title', class: 'nt-col-title' },
    { key: 'updated_at', label: 'Edited', class: 'nt-col-meta' },
];
const listPage = ref(1);

function fmtDate(iso) {
    if (!iso) return '';
    try {
        return new Date(iso).toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric' });
    } catch {
        return iso;
    }
}

// ----- Folder accordion -----
// Top-level note folders hang off the notes root; normalize their parent to
// null so FolderAccordion's tree walk (rooted at null) covers both root
// children and any orphaned top-level folders.
const accordionFolders = computed(() => props.folders.map((f) => ({
    ...f,
    parent_id: f.parent_id === props.rootId ? null : f.parent_id,
})));
// Note counts per folder, shown as row badges.
const folderCounts = computed(() => {
    const map = {};
    for (const n of props.notes) {
        map[n.parent_id] = (map[n.parent_id] ?? 0) + 1;
    }
    return map;
});
// Auto-expand the path from the root to the selected folder.
const openFolderIds = computed(() => {
    const byId = Object.fromEntries(accordionFolders.value.map((f) => [f.id, f]));
    const ids = new Set();
    let id = selectedFolder.value;
    while (id != null && byId[id]) {
        ids.add(id);
        id = byId[id].parent_id;
    }
    return ids;
});

// ----- Breadcrumb -----
const selectedFolderName = computed(() =>
    props.folders.find((f) => f.id === selectedFolder.value)?.name ?? null);
const breadcrumbItems = computed(() => [
    { text: 'Notes', kind: 'root', href: '/notes', active: selectedFolder.value === null && activeTag.value === null },
    ...(selectedFolderName.value !== null
        ? [{ text: selectedFolderName.value, kind: 'folder', href: '/notes', active: true }]
        : []),
    ...(activeTag.value !== null
        ? [{ text: `#${activeTag.value}`, kind: 'tag', href: '/notes', active: true }]
        : []),
]);
function onBreadcrumb({ item, event }) {
    event?.preventDefault?.();
    if (!item.active) selectFolder(null);
}

function selectFolder(id) {
    selectedFolder.value = id;
    activeTag.value = null;
    activePane.value = 'contents'; // mobile: reveal the list after picking a folder
}
function selectTag(id) {
    activeTag.value = id;
    selectedFolder.value = null;
    activePane.value = 'contents'; // mobile: reveal the list after picking a tag
}

async function newFolder() {
    const name = await prompt({ title: 'New folder', message: 'Folder name:', confirmLabel: 'Create' });
    if (!name || name.trim() === '') return;
    router.post('/notes/folders', { name: name.trim(), parent_id: selectedFolder.value }, { preserveScroll: true });
}

const selectedNote = computed(() => props.notes.find((n) => n.id === selectedId.value) || null);

const editorRef = ref(null);
// Heading outline of the open note, indented by heading level via CSS.
const outline = computed(() => extractHeadings(content.value));
function jumpToHeading(line) {
    editorRef.value?.jumpToLine(line);
}

async function loadContent(note) {
    suppressSave = true;
    saveState.value = 'idle';
    // Race guard: a newer selection must win even if an older fetch resolves later.
    const token = ++loadSeq;
    try {
        const text = await getText(note.raw_url);
        if (token !== loadSeq) return;
        content.value = text;
    } catch {
        if (token !== loadSeq) return;
        content.value = '';
    } finally {
        if (token === loadSeq) {
            // Let the editor settle before re-enabling autosave.
            if (suppressTimer) clearTimeout(suppressTimer);
            suppressTimer = setTimeout(() => { suppressSave = false; }, 0);
        }
    }
}

onBeforeUnmount(() => {
    unmounted = true;
    if (saveTimer) clearTimeout(saveTimer);
    if (suppressTimer) clearTimeout(suppressTimer);
    window.removeEventListener('keydown', onKey);
});

function selectNote(id) {
    selectedId.value = id;
    const note = props.notes.find((n) => n.id === id);
    if (note) { loadContent(note); activePane.value = 'detail'; }
}

function clearContentSelection() {
    noteClearSel();
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

function newNote() {
    router.post('/notes', { name: 'Untitled', parent_id: selectedFolder.value }, { preserveScroll: true });
}

watch(content, () => {
    if (suppressSave || !selectedId.value) return;
    saveState.value = 'saving'; // clears 'error' on next keystroke
    clearTimeout(saveTimer);
    saveTimer = setTimeout(autosave, 800);
});

async function autosave(extra = {}) {
    if (!selectedId.value) return;
    saveState.value = 'saving';
    try {
        await http.put(`/notes/${selectedId.value}/autosave`, { content: content.value, ...extra });
        if (!unmounted) saveState.value = 'saved';
    } catch {
        if (!unmounted) saveState.value = 'error';
    }
}

function saveVersion() {
    autosave({ checkpoint: true });
}

function toggleStar(note) {
    router.post(`/files/${note.id}/star`, {}, { preserveScroll: true, preserveState: true });
}

async function renameNote() {
    const note = selectedNote.value;
    if (!note) return;
    const title = await prompt({
        title: 'Rename note',
        message: 'New title:',
        value: note.title,
        confirmLabel: 'Rename',
    });
    if (!title || title.trim() === '' || title.trim() === note.title) return;
    router.put(`/notes/${note.id}/rename`, { title: title.trim() }, { preserveScroll: true });
}

onMounted(() => {
    if (props.createTitle) {
        router.post('/notes', { name: props.createTitle }, { preserveScroll: true });
    } else if (selectedId.value) {
        selectNote(selectedId.value);
    }
});
</script>

<template>
    <ShellLayout v-model:active-pane="activePane" :detail-visible="!!selectedNote">
        <template #viewNav>
            <div class="px-1 pt-1">
                <button
                    type="button"
                    class="side-row w-100 text-start d-flex align-items-center gap-2 px-2 py-1 rounded border-0 bg-transparent"
                    :class="{ active: selectedFolder === null && activeTag === null }"
                    @click="selectFolder(null)"
                >
                    <VibeIcon icon="journals" />
                    <span>All Notes</span>
                </button>
            </div>
            <FolderAccordion
                :folders="accordionFolders"
                :selected-id="selectedFolder"
                :open-ids="openFolderIds"
                :counts="folderCounts"
                @select-folder="selectFolder"
                @new-folder="newFolder"
            />
            <NotesSidebar
                :tags="tags"
                :active-tag="activeTag"
                @select-tag="selectTag"
            />
        </template>

        <!-- Breadcrumb + actions share the top-bar line: New note is the single
             solid-primary CTA. -->
        <template #topBar>
            <div class="d-flex align-items-center gap-2 p-1">
                <VibeBreadcrumb :items="breadcrumbItems" class="breadcrumb mb-0 pb-0 text-truncate min-w-0" @item-click="onBreadcrumb">
                    <template #item="{ item }">
                        <VibeIcon :icon="item.kind === 'root' ? 'journal-text' : (item.kind === 'tag' ? 'tag-fill' : 'folder2')" class="me-1" /><span :title="item.text">{{ item.text }}</span>
                    </template>
                </VibeBreadcrumb>
                <div class="d-flex align-items-center gap-2 ms-auto flex-shrink-0">
                    <VibeButton size="sm" variant="primary" title="New note" aria-label="New note" @click="newNote">
                        <VibeIcon icon="plus-lg" />
                    </VibeButton>
                </div>
            </div>
        </template>

        <template #contents>
            <SelectBar :count="selectedNotes.length" class="mx-2 mt-2" @clear="noteClearSel">
                <VibeButton variant="danger" size="sm" outline @click="bulkDeleteNotes">
                    <VibeIcon icon="trash" class="me-1" />Delete
                </VibeButton>
            </SelectBar>

            <LoadingSkeleton v-if="loading" :rows="8" :cols="3" />

            <div
                v-else
                class="nt-table-wrap overflow-auto flex-grow-1 px-2 pt-1"
                :class="{ 'nt-has-selection': noteSelectedIds.size > 0 }"
                @click.self="clearContentSelection"
            >
                <VibeDataTable
                    v-if="filteredNotes.length"
                    :items="filteredNotes"
                    :columns="tableColumns"
                    row-key="id"
                    hover
                    small
                    clickable
                    search-placeholder="Search notes…"
                    :show-per-page="false"
                    :per-page="50"
                    v-model:current-page="listPage"
                    sort-by="title"
                    class="nt-table"
                    @row-clicked="(n) => selectNote(n.id)"
                >
                    <template #cell(_select)="{ item }">
                        <VibeFormCheckbox
                            class="nt-select-check"
                            :model-value="noteIsSelected(item.id)"
                            :aria-label="noteIsSelected(item.id) ? `Deselect ${item.title}` : `Select ${item.title}`"
                            @update:model-value="noteToggle(item.id)"
                            @click.stop
                        />
                    </template>
                    <template #cell(title)="{ item }">
                        <div class="d-flex align-items-center min-w-0">
                            <VibeIcon icon="journal-text" class="me-2 text-muted flex-shrink-0" />
                            <span class="text-truncate" :class="{ 'fw-semibold': item.id === selectedId }" :title="item.title">{{ item.title }}</span>
                            <VibeIcon v-if="item.starred" icon="star-fill" class="text-warning small ms-2 flex-shrink-0" title="Starred" />
                            <span v-for="tag in item.tags" :key="tag.id" class="badge text-bg-light ms-2 flex-shrink-0">#{{ tag.name }}</span>
                        </div>
                    </template>
                    <template #cell(updated_at)="{ item }">
                        <span class="text-muted small text-nowrap">{{ fmtDate(item.updated_at) }}</span>
                    </template>
                </VibeDataTable>
                <EmptyState v-else icon="journal-text" title="No notes yet" hint="Create your first note to get started." />
            </div>
        </template>

        <template #detail>
            <template v-if="selectedNote">
                <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom flex-shrink-0">
                    <VibeButton
                        variant="link"
                        size="sm"
                        class="p-0 fw-semibold text-truncate text-decoration-none text-body note-title"
                        title="Rename note"
                        @click="renameNote"
                    >
                        {{ selectedNote.title }}
                        <VibeIcon icon="pencil" class="ms-1 small text-muted" />
                    </VibeButton>
                    <div class="d-flex align-items-center gap-2">
                        <SaveIndicator :state="saveState" />
                        <VibeButton size="sm" variant="light" :title="selectedNote.starred ? 'Unstar' : 'Star'" :aria-label="selectedNote.starred ? 'Unstar' : 'Star'" @click="toggleStar(selectedNote)">
                            <VibeIcon :icon="selectedNote.starred ? 'star-fill' : 'star'" :class="{ 'text-warning': selectedNote.starred }" />
                        </VibeButton>
                        <VibeDropdown
                            size="sm"
                            variant="light"
                            menu-end
                            title="Outline"
                            :disabled="!outline.length"
                            :items="outline"
                            @item-click="jumpToHeading($event.item.line)"
                        >
                            <template #button><VibeIcon icon="list-nested" class="me-1" />Outline</template>
                            <template #item="{ item }">
                                <span :class="`outline-h-${item.level}`">{{ item.text }}</span>
                            </template>
                        </VibeDropdown>
                        <VibeButton size="sm" variant="light" title="Save a version" @click="saveVersion">
                            <VibeIcon icon="bookmark-plus" class="me-1" />Save version
                        </VibeButton>
                    </div>
                </div>
                <div class="notes-editor-body">
                    <MarkdownEditor ref="editorRef" v-model="content" enable-links />
                </div>
                <BacklinksPanel :note-id="selectedId" @open="selectNote" />
            </template>
        </template>
    </ShellLayout>
</template>

<style scoped>
.min-w-0 {
    min-width: 0;
}
.notes-editor-body {
    flex: 1 1 auto;
    min-height: 0;
    min-width: 0;
    overflow: hidden;
}
/* Explorer table chrome (mirrors the Files table). */
.nt-table-wrap :deep(thead th) {
    position: sticky;
    top: 0;
    z-index: 1;
    background: var(--bs-body-bg);
}
.nt-table-wrap :deep(td) {
    vertical-align: middle;
}
.nt-table-wrap :deep(.nt-col-select) {
    width: 1%;
    white-space: nowrap;
}
.nt-table-wrap :deep(.nt-col-meta) {
    width: 1%;
    white-space: nowrap;
}
.nt-table-wrap :deep(.nt-select-check) {
    opacity: 0;
    transition: opacity 0.15s;
    cursor: pointer;
}
.nt-table-wrap :deep(tr:hover .nt-select-check),
.nt-table-wrap :deep(tr:focus-within .nt-select-check),
.nt-table-wrap.nt-has-selection :deep(.nt-select-check) {
    opacity: 1;
}
/* Outline item indentation by heading level (1-6). */
.outline-h-1 { padding-left: 0; }
.outline-h-2 { padding-left: 0.85rem; }
.outline-h-3 { padding-left: 1.7rem; }
.outline-h-4 { padding-left: 2.55rem; }
.outline-h-5 { padding-left: 3.4rem; }
.outline-h-6 { padding-left: 4.25rem; }
</style>
