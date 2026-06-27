<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import ThreePane from '../../Components/ThreePane.vue';
import NotesSidebar from '../../Components/Notes/NotesSidebar.vue';
import NotesList from '../../Components/Notes/NotesList.vue';
import BacklinksPanel from '../../Components/Notes/BacklinksPanel.vue';
import MarkdownEditor from '../../Components/MarkdownEditor.vue';
import SelectBar from '../../Components/SelectBar.vue';
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
const sortOrder = ref('name-asc');
const activePane = ref(props.open ? 'detail' : 'list');
let suppressSave = false;
let saveTimer = null;
let suppressTimer = null;
let loadSeq = 0;
let unmounted = false;

const { loading } = usePageLoading();
const notesRef = computed(() => props.notes);
const { selectMode: noteSelectMode, selectedItems: selectedNotes, isSelected: noteIsSelected, toggleSel: noteToggle, clearSelection: noteClearSel } = useSelection(notesRef, (n) => selectNote(n.id));

async function bulkDeleteNotes() {
    if (!await confirm({ title: `Delete ${selectedNotes.value.length} note(s)`, message: 'Move selected notes to trash?', confirmLabel: 'Delete', variant: 'danger' })) return;
    for (const n of selectedNotes.value) {
        router.delete(`/notes/${n.id}`, { preserveScroll: true });
    }
    toast.push(`${selectedNotes.value.length} note(s) deleted`);
    noteClearSel();
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
    const sorted = [...list];
    if (sortOrder.value === 'name-asc') sorted.sort((a, b) => a.title.localeCompare(b.title));
    else if (sortOrder.value === 'name-desc') sorted.sort((a, b) => b.title.localeCompare(a.title));
    else if (sortOrder.value === 'date') sorted.sort((a, b) => b.updated_at.localeCompare(a.updated_at));
    return sorted;
});

function selectFolder(id) {
    selectedFolder.value = id;
    activeTag.value = null;
}
function selectTag(id) {
    activeTag.value = id;
    selectedFolder.value = null;
}

async function newFolder() {
    const name = await prompt({ title: 'New folder', message: 'Folder name:', confirmLabel: 'Create' });
    if (!name || name.trim() === '') return;
    router.post('/notes/folders', { name: name.trim(), parent_id: selectedFolder.value }, { preserveScroll: true });
}

const selectedNote = computed(() => props.notes.find((n) => n.id === selectedId.value) || null);

const editorRef = ref(null);
// Heading outline of the open note, with each item indented by heading level.
const outline = computed(() =>
    extractHeadings(content.value).map((h) => ({
        ...h,
        text: ' '.repeat((h.level - 1) * 2) + h.text,
    }))
);
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
});

function selectNote(id) {
    selectedId.value = id;
    const note = props.notes.find((n) => n.id === id);
    if (note) { loadContent(note); activePane.value = 'detail'; }
}

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
    <AppLayout>
        <LoadingSkeleton v-if="loading" :rows="8" :cols="3" />
        <ThreePane v-else v-model:activePane="activePane">
            <template #sidebar>
                <NotesSidebar
                    :folders="folders"
                    :root-id="rootId"
                    :tags="tags"
                    :active-tag="activeTag"
                    :selected-folder="selectedFolder"
                    @select-folder="selectFolder"
                    @select-tag="selectTag"
                    @new-folder="newFolder"
                />
            </template>

            <template #list>
                <SelectBar :count="selectedNotes.length" @clear="noteClearSel">
                    <VibeButton variant="danger" size="sm" outline @click="bulkDeleteNotes">
                        <VibeIcon icon="trash" class="me-1" />Delete
                    </VibeButton>
                </SelectBar>
                <NotesList
                    :notes="filteredNotes"
                    :selected-id="selectedId"
                    :sort="sortOrder"
                    :select-mode="noteSelectMode"
                    :is-selected="noteIsSelected"
                    @select="selectNote"
                    @new="newNote"
                    @update:sort="sortOrder = $event"
                    @toggle-select="noteToggle"
                    @update:select-mode="noteSelectMode = $event"
                />
            </template>

            <template #detail>
                <template v-if="selectedNote">
                    <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom flex-shrink-0">
                        <button
                            type="button"
                            class="btn btn-link p-0 fw-semibold text-truncate text-decoration-none text-body note-title"
                            title="Rename note"
                            @click="renameNote"
                        >
                            {{ selectedNote.title }}
                            <VibeIcon icon="pencil" class="ms-1 small text-muted" />
                        </button>
                        <div class="d-flex align-items-center gap-2">
                            <SaveIndicator :state="saveState" />
                            <VibeButton size="sm" variant="secondary" outline :title="selectedNote.starred ? 'Unstar' : 'Star'" :aria-label="selectedNote.starred ? 'Unstar' : 'Star'" @click="toggleStar(selectedNote)">
                                <VibeIcon :icon="selectedNote.starred ? 'star-fill' : 'star'" :class="{ 'text-warning': selectedNote.starred }" />
                            </VibeButton>
                            <VibeDropdown
                                size="sm"
                                variant="secondary"
                                outline
                                menu-end
                                title="Outline"
                                :disabled="!outline.length"
                                :items="outline"
                                @item-click="jumpToHeading($event.item.line)"
                            >
                                <template #button><VibeIcon icon="list-nested" class="me-1" />Outline</template>
                                <template #item="{ item }">
                                    <span class="font-monospace text-muted me-1" style="white-space: pre">{{ item.text }}</span>
                                </template>
                            </VibeDropdown>
                            <VibeButton size="sm" variant="secondary" outline title="Save a version" @click="saveVersion">
                                <VibeIcon icon="bookmark-plus" class="me-1" />Save version
                            </VibeButton>
                        </div>
                    </div>
                    <div class="notes-editor-body">
                        <MarkdownEditor ref="editorRef" v-model="content" enable-links />
                    </div>
                    <BacklinksPanel :note-id="selectedId" @open="selectNote" />
                </template>

                <div v-else class="d-flex flex-column align-items-center justify-content-center h-100 text-muted">
                    <VibeIcon icon="journal-text" class="fs-1 mb-2" />
                    <p class="mb-0">Select a note, or create a new one.</p>
                </div>
            </template>
        </ThreePane>
    </AppLayout>
</template>

<style scoped>
.notes-editor-body {
    flex: 1 1 auto;
    min-height: 0;
    overflow: hidden;
}
</style>
