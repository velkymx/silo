<script setup lang="ts">
// FE-P1-39: No chrome. Slots into pattern B (full-page route) at
// `Pages/Files/Editor.vue`. The parent provides the top bar, save flow, and
// the error/load states.
import { onMounted, onBeforeUnmount, ref } from 'vue';
import { getArrayBuffer } from '../lib/http';

const props = defineProps<{
    url?: string | null;
    name?: string;
}>();
const emit = defineEmits<{
    (e: 'ready'): void;
    (e: 'error', message: string): void;
}>();

const DOCX_MIME = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
const toolbarId = `sd-toolbar-${Math.random().toString(36).slice(2)}`;
const editorEl = ref<HTMLElement | null>(null);
let superdoc: any = null;

async function load() {
    try {
        const { SuperDoc } = await import('@harbour-enterprises/superdoc');
        await import('@harbour-enterprises/superdoc/style.css');

        const config = {
            selector: editorEl.value,
            toolbar: `#${toolbarId}`,
            documentMode: 'editing',
            pagination: true,
            rulers: true,
            // Continuously fit the page to the available canvas width.
            zoom: { mode: 'fit-width' },
            onReady: () => emit('ready'),
        };

        // Load existing bytes, or start a blank document when creating.
        if (props.url) {
            const bytes = await getArrayBuffer(props.url);
            (config as any).document = new File([bytes], props.name || 'document.docx', { type: DOCX_MIME });
        }

        superdoc = new SuperDoc(config as any);
    } catch (e) {
        emit('error', 'Could not open this document.');
    }
}

// Export the live document back to a .docx Blob (no auto-download).
async function serialize() {
    if (!superdoc) throw new Error('Document not loaded');
    return await superdoc.export({ exportType: 'docx', triggerDownload: false });
}

defineExpose({ serialize });

onMounted(load);
onBeforeUnmount(() => {
    try { superdoc?.destroy?.(); } catch (e) { /* noop */ }
    superdoc = null;
});
</script>

<template>
    <div class="docx-editor">
        <div :id="toolbarId" class="docx-toolbar"></div>
        <div ref="editorEl" class="docx-canvas"></div>
    </div>
</template>

<style>
.docx-editor {
    display: flex;
    flex-direction: column;
    height: 100%;
}
.docx-canvas {
    flex: 1 1 auto;
    overflow: auto;
    min-height: 0;
    background: var(--bs-tertiary-bg);
}
/* In native fullscreen the editor surface owns the whole viewport. */
.docx-editor:fullscreen {
    background: var(--bs-body-bg);
    padding: 0.5rem;
}
</style>
