<script setup lang="ts">
import { ref, watch, onMounted, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';
import MarkdownEditor from './MarkdownEditor.vue';
import { getText } from '../lib/http';

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

watch(open, async (v) => {
    if (!v) return;
    content.value = '';
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
        if (seq === loadSeq) content.value = text;
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
            <VibeFormGroup v-if="creating" label="File name" class="mb-3">
                <VibeFormInput v-model="name" placeholder="untitled.md" />
            </VibeFormGroup>
            <MarkdownEditor v-if="kind === 'markdown'" v-model="content" />
            <VibeFormWysiwyg v-else v-model="content" height="60vh" />
        </template>
        <template #footer>
            <VibeButton variant="secondary" outline @click="open = false">Cancel</VibeButton>
            <VibeButton variant="primary" :disabled="saving || loading" @click="save">
                <VibeIcon icon="save" class="me-1" />{{ creating ? 'Create' : 'Save' }}
            </VibeButton>
        </template>
    </VibeModal>
</template>
