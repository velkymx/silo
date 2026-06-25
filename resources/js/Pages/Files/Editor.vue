<script setup>
import { computed, ref, onMounted, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';
import SpreadsheetEditor from '../../Components/SpreadsheetEditor.vue';
import DocxEditor from '../../Components/DocxEditor.vue';
import AppFormGroup from '../../Components/AppFormGroup.vue';

const props = defineProps({
    file: { type: Object, default: null },
    // Present when creating a brand-new blank document.
    create: { type: Object, default: null },
});

const creating = computed(() => !props.file && !!props.create);
const docType = computed(() => props.file?.type || props.create?.type);
const docName = computed(() => props.file?.name || props.create?.name);
const parentId = computed(() => props.file?.parent_id ?? props.create?.parent_id ?? null);

const sheetTypes = ['xlsx', 'xls', 'csv', 'ods'];
const kind = computed(() => {
    if (docType.value === 'docx') return 'docx';
    if (sheetTypes.includes(docType.value)) return 'sheet';
    return 'unsupported';
});

const editorRef = ref(null);
const surfaceRef = ref(null);
const loadError = ref('');
const ready = ref(false);
const isFullscreen = ref(false);

function toggleFullscreen() {
    if (document.fullscreenElement) document.exitFullscreen();
    else surfaceRef.value?.requestFullscreen?.();
}
function onFsChange() {
    isFullscreen.value = !!document.fullscreenElement;
}
onMounted(() => document.addEventListener('fullscreenchange', onFsChange));
onBeforeUnmount(() => document.removeEventListener('fullscreenchange', onFsChange));

const saveOpen = ref(false);
const note = ref('');
const newName = ref(props.create?.name || '');
const saving = ref(false);

function back() {
    router.get('/', parentId.value ? { folder: parentId.value } : {});
}

function openSave() {
    newName.value = props.create?.name || docName.value;
    saveOpen.value = true;
}

async function commitSave() {
    // Never serialize an editor that hasn't signalled ready or has errored.
    if (!ready.value || loadError.value || typeof editorRef.value?.serialize !== 'function') return;
    saving.value = true;
    try {
        const blob = await editorRef.value.serialize();
        const form = new FormData();
        const finish = {
            forceFormData: true,
            onFinish: () => { saving.value = false; saveOpen.value = false; },
            onError: () => { loadError.value = 'Could not save this document.'; },
        };

        if (creating.value) {
            form.append('file', new File([blob], newName.value || docName.value));
            form.append('name', newName.value || docName.value);
            form.append('type', docType.value);
            if (parentId.value) form.append('parent_id', parentId.value);
            router.post('/files/document', form, finish);
        } else {
            form.append('file', new File([blob], props.file.name));
            if (note.value.trim()) form.append('note', note.value.trim());
            form.append('_method', 'put');
            router.post(`/files/${props.file.id}/content`, form, finish);
        }
    } catch (e) {
        saving.value = false;
        loadError.value = 'Could not save this document.';
    }
}
</script>

<template>
    <div class="editor-page d-flex flex-column vh-100">
        <!-- Top bar -->
        <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom bg-body">
            <VibeButton variant="light" size="sm" @click="back">
                <VibeIcon icon="arrow-left" class="me-1" />Back
            </VibeButton>
            <div class="fw-semibold text-truncate">
                <VibeIcon icon="pencil-square" class="me-1 text-primary" />{{ docName }}
            </div>
            <VibeBadge v-if="creating" variant="success" class="ms-1">New</VibeBadge>
            <VibeBadge v-else variant="secondary" class="ms-1">v{{ file.version }}</VibeBadge>
            <div class="ms-auto d-flex gap-2">
                <VibeButton variant="light" size="sm" :title="isFullscreen ? 'Exit full screen' : 'Full screen'" @click="toggleFullscreen">
                    <VibeIcon :icon="isFullscreen ? 'fullscreen-exit' : 'arrows-fullscreen'" class="me-1" />
                    {{ isFullscreen ? 'Exit' : 'Full screen' }}
                </VibeButton>
                <VibeButton variant="primary" size="sm" :disabled="!ready || kind === 'unsupported' || !!loadError" @click="openSave">
                    <VibeIcon icon="check2" class="me-1" />{{ creating ? 'Create' : 'Save' }}
                </VibeButton>
            </div>
        </div>

        <!-- Editor surface -->
        <div ref="surfaceRef" class="editor-surface flex-grow-1 overflow-auto p-2 bg-body-tertiary">
            <VibeAlert v-if="loadError" variant="danger">{{ loadError }}</VibeAlert>

            <SpreadsheetEditor
                v-if="kind === 'sheet'"
                ref="editorRef"
                :url="file?.raw_url || null"
                :type="docType"
                @ready="ready = true"
                @error="loadError = $event"
            />
            <DocxEditor
                v-else-if="kind === 'docx'"
                ref="editorRef"
                :url="file?.raw_url || null"
                :name="docName"
                @ready="ready = true"
                @error="loadError = $event"
            />
            <VibeAlert v-else variant="warning">
                No editor available for “{{ docType }}” files.
            </VibeAlert>
        </div>

        <!-- GitHub-style save popup -->
        <VibeModal v-model="saveOpen" :title="creating ? 'Create document' : 'Save changes'" centered hide-footer>
            <template v-if="creating">
                <AppFormGroup label="File name">
                    <VibeFormInput v-model="newName" :placeholder="`Untitled.${docType}`" />
                </AppFormGroup>
            </template>
            <template v-else>
                <p class="text-muted small mb-2">
                    Describe what changed (optional). This creates a new version of
                    <strong>{{ file.name }}</strong>.
                </p>
                <AppFormGroup label="What changed?">
                    <VibeFormTextarea v-model="note" :rows="3" placeholder="e.g. Updated Q3 totals, fixed typo…" />
                </AppFormGroup>
            </template>
            <div class="d-flex justify-content-end gap-2 mt-3">
                <VibeButton variant="light" @click="saveOpen = false">Cancel</VibeButton>
                <VibeButton variant="primary" :disabled="saving" @click="commitSave">
                    <VibeSpinner v-if="saving" size="sm" class="me-1" />
                    {{ creating ? 'Create' : 'Save version' }}
                </VibeButton>
            </div>
        </VibeModal>
    </div>
</template>

<style scoped>
.editor-surface:fullscreen {
    padding: 0.5rem;
    background: var(--bs-body-bg);
}
.editor-surface:fullscreen > * {
    height: 100%;
}
</style>
