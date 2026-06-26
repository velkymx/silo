<script setup lang="ts">
import { ref, watch } from 'vue';
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
const saving = ref(false);

watch(open, async (v) => {
    if (!v) return;
    content.value = '';
    if (props.creating) {
        name.value = 'untitled.md';
        loading.value = false;
        return;
    }
    loading.value = true;
    try {
        content.value = await getText(`/raw/${props.item!.id}`);
    } finally {
        loading.value = false;
    }
});

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
