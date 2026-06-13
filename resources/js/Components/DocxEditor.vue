<script setup>
import { onMounted, onBeforeUnmount, ref } from 'vue';

const props = defineProps({
    url: { type: String, required: true },
    name: { type: String, default: 'document.docx' },
});
const emit = defineEmits(['ready', 'error']);

const DOCX_MIME = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
const toolbarId = `sd-toolbar-${Math.random().toString(36).slice(2)}`;
const editorEl = ref(null);
let superdoc = null;

async function load() {
    try {
        const res = await window.axios.get(props.url, { responseType: 'arraybuffer' });
        const file = new File([res.data], props.name, { type: DOCX_MIME });

        const { SuperDoc } = await import('@harbour-enterprises/superdoc');
        await import('@harbour-enterprises/superdoc/style.css');

        superdoc = new SuperDoc({
            selector: editorEl.value,
            toolbar: `#${toolbarId}`,
            documentMode: 'editing',
            document: file,
            pagination: true,
            rulers: true,
            // Continuously fit the page to the available canvas width.
            zoom: { mode: 'fit-width' },
            onReady: () => emit('ready'),
        });
    } catch (e) {
        emit('error', 'Could not open this document.');
    }
}

// Export the live document back to a .docx Blob (no auto-download).
async function serialize() {
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
