<script setup>
import { onMounted, onBeforeUnmount, ref, watch } from 'vue';
import Viewer from '@toast-ui/editor/viewer';
import '@toast-ui/editor/dist/toastui-editor-viewer.css';
import '@toast-ui/editor/dist/theme/toastui-editor-dark.css';
import { getText } from '../lib/http';
import { linkifyNotes } from '../lib/linkifyNotes';

const props = defineProps({
    url: { type: String, required: true },
    // Rewrite [[wikilinks]] / @mentions into clickable markdown (Notes surface).
    notes: { type: Boolean, default: false },
});

const el = ref(null);
let viewer = null;
const error = ref('');

// Race guard: a newer `url` must win even if an older fetch resolves later.
let loadSeq = 0;

function isDark() {
    return document.documentElement.getAttribute('data-bs-theme') === 'dark';
}

async function load() {
    error.value = '';
    const token = ++loadSeq;
    try {
        const raw = await getText(props.url);
        if (token !== loadSeq || !el.value) return;
        const markdown = props.notes ? linkifyNotes(raw) : raw;

        viewer?.destroy();
        viewer = new Viewer({
            el: el.value,
            initialValue: markdown,
            theme: isDark() ? 'dark' : 'light',
        });
    } catch (e) {
        if (token === loadSeq) error.value = 'Could not render this file.';
    }
}

onMounted(load);
watch(() => props.url, load);
onBeforeUnmount(() => {
    loadSeq++;
    viewer?.destroy();
    viewer = null;
});
</script>

<template>
    <div>
        <VibeAlert v-if="error" variant="warning">{{ error }}</VibeAlert>
        <div ref="el" class="md-viewer text-start"></div>
    </div>
</template>

<style>
.md-viewer {
    max-height: 100%;
    overflow: auto;
}
</style>
