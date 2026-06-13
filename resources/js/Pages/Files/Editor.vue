<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import SpreadsheetEditor from '../../Components/SpreadsheetEditor.vue';
import DocxEditor from '../../Components/DocxEditor.vue';

const props = defineProps({
    file: { type: Object, required: true },
});

const sheetTypes = ['xlsx', 'xls', 'csv', 'ods'];
const kind = computed(() => {
    if (props.file.type === 'docx') return 'docx';
    if (sheetTypes.includes(props.file.type)) return 'sheet';
    return 'unsupported';
});

const editorRef = ref(null);
const loadError = ref('');
const ready = ref(false);

const noteOpen = ref(false);
const note = ref('');
const saving = ref(false);

function back() {
    router.get('/', props.file.parent_id ? { folder: props.file.parent_id } : {});
}

async function commitSave() {
    if (!editorRef.value?.serialize) return;
    saving.value = true;
    try {
        const blob = await editorRef.value.serialize();
        const form = new FormData();
        form.append('file', new File([blob], props.file.name));
        if (note.value.trim()) form.append('note', note.value.trim());
        form.append('_method', 'put');

        router.post(`/files/${props.file.id}/content`, form, {
            forceFormData: true,
            onFinish: () => {
                saving.value = false;
                noteOpen.value = false;
            },
        });
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
                <VibeIcon icon="pencil-square" class="me-1 text-primary" />{{ file.name }}
            </div>
            <VibeBadge variant="secondary" class="ms-1">v{{ file.version }}</VibeBadge>
            <div class="ms-auto d-flex gap-2">
                <VibeButton variant="light" size="sm" @click="back">Cancel</VibeButton>
                <VibeButton variant="primary" size="sm" :disabled="!ready || kind === 'unsupported'" @click="noteOpen = true">
                    <VibeIcon icon="check2" class="me-1" />Save
                </VibeButton>
            </div>
        </div>

        <!-- Editor surface -->
        <div class="flex-grow-1 overflow-auto p-2 bg-body-tertiary">
            <VibeAlert v-if="loadError" variant="danger">{{ loadError }}</VibeAlert>

            <SpreadsheetEditor
                v-if="kind === 'sheet'"
                ref="editorRef"
                :url="file.raw_url"
                :type="file.type"
                @ready="ready = true"
                @error="loadError = $event"
            />
            <DocxEditor
                v-else-if="kind === 'docx'"
                ref="editorRef"
                :url="file.raw_url"
                :name="file.name"
                @ready="ready = true"
                @error="loadError = $event"
            />
            <VibeAlert v-else variant="warning">
                No editor available for “{{ file.type }}” files.
            </VibeAlert>
        </div>

        <!-- GitHub-style commit-note popup -->
        <VibeModal v-model="noteOpen" title="Save changes" centered hide-footer>
            <p class="text-muted small mb-2">
                Describe what changed (optional). This creates a new version of
                <strong>{{ file.name }}</strong>.
            </p>
            <VibeFormGroup label="What changed?">
                <VibeFormTextarea
                    v-model="note"
                    :rows="3"
                    placeholder="e.g. Updated Q3 totals, fixed typo in intro…"
                />
            </VibeFormGroup>
            <div class="d-flex justify-content-end gap-2 mt-3">
                <VibeButton variant="light" @click="noteOpen = false">Cancel</VibeButton>
                <VibeButton variant="primary" :disabled="saving" @click="commitSave">
                    <VibeSpinner v-if="saving" size="sm" class="me-1" />
                    Save version
                </VibeButton>
            </div>
        </VibeModal>
    </div>
</template>
