<script setup lang="ts">
// FE-P1-39: Chrome pattern A — inline VibeModal for text editors (markdown/html).
// Pattern B (full-page route for binary office files) lives in `Pages/Files/Editor.vue`
// and embeds `SpreadsheetEditor`/`DocxEditor`, which have no chrome of their own.
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';
import MarkdownEditor from './MarkdownEditor.vue';
import { getText } from '../lib/http';
import { useDirtyGuard } from '../composables/useDirtyGuard';

interface FileLike { id: number; name: string }

const open = defineModel<boolean>({ required: true });
const props = defineProps<{
    item: FileLike | null;
    creating: boolean;
    kind: 'markdown' | 'html';
    parentId: number | null;
}>();

const content = ref('');
const name = ref('');
const loading = ref(false);
const loadError = ref('');
const saving = ref(false);
let loadSeq = 0;

const originalContent = ref('');
const isDirty = computed(() => !loading.value && content.value !== originalContent.value);
const { guardedClose } = useDirtyGuard(() => isDirty.value);

function tryClose() {
    guardedClose(() => { open.value = false; });
}

watch(open, async (v) => {
    if (!v) return;
    content.value = '';
    originalContent.value = '';
    loadError.value = '';
    if (props.creating) {
        name.value = 'untitled.md';
        loading.value = false;
        return;
    }
    const seq = ++loadSeq;
    loading.value = true;
    try {
        const text = await getText(`/raw/${props.item!.id}`);
        if (seq === loadSeq) { content.value = text; originalContent.value = text; }
    } catch {
        if (seq === loadSeq) loadError.value = 'Could not load file. Please close and try again.';
    } finally {
        if (seq === loadSeq) loading.value = false;
    }
});

function onKeydown(e: KeyboardEvent): void {
    if ((e.metaKey || e.ctrlKey) && e.key === 'Enter' && open.value && !saving.value && !loading.value) {
        e.preventDefault();
        save();
    }
}
onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));

function save(): void {
    saving.value = true;
    const done = {
        preserveScroll: true,
        onSuccess: () => { open.value = false; },
        onFinish: () => { saving.value = false; },
    };
    if (props.creating) {
        router.post('/files/text', { name: name.value, content: content.value, parent_id: props.parentId }, done);
    } else {
        router.put(`/files/${props.item!.id}/content`, { content: content.value }, done);
    }
}
</script>

<template>
    <VibeModal
        v-model="open"
        :title="creating ? 'New Markdown file' : `Edit — ${item?.name || ''}`"
        fullscreen
    >
        <div v-if="loading" class="text-center py-5 text-muted">
            <VibeSpinner class="me-2" />Loading…
        </div>
        <div v-else-if="loadError" class="alert alert-danger" role="alert">{{ loadError }}</div>
        <template v-else>
            <!-- Full-height editing surface: the editor fills whatever the
                 fullscreen modal body doesn't spend on the name field. -->
            <div class="d-flex flex-column h-100">
                <VibeFormGroup v-if="creating" label="File name" class="mb-3 flex-shrink-0">
                    <VibeFormInput v-model="name" placeholder="untitled.md" />
                </VibeFormGroup>
                <MarkdownEditor v-if="kind === 'markdown'" v-model="content" fill />
                <VibeFormWysiwyg v-else v-model="content" height="100%" class="flex-grow-1 min-h-0" />
            </div>
        </template>
        <template #footer>
            <VibeButton variant="secondary" outline @click="tryClose">Cancel</VibeButton>
            <VibeButton variant="primary" :disabled="saving || loading" @click="save">
                <VibeIcon icon="save" class="me-1" />{{ creating ? 'Create' : 'Save' }}
            </VibeButton>
        </template>
    </VibeModal>
</template>

<style scoped>
.min-h-0 {
    min-height: 0;
}
</style>
