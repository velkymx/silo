<script setup>
import { onMounted, onBeforeUnmount, ref, watch } from 'vue';
import Viewer from '@toast-ui/editor/viewer';
import '@toast-ui/editor/dist/toastui-editor-viewer.css';
import '@toast-ui/editor/dist/theme/toastui-editor-dark.css';

const props = defineProps({
    url: { type: String, required: true },
});

const el = ref(null);
let viewer = null;
const error = ref('');

function isDark() {
    return document.documentElement.getAttribute('data-bs-theme') === 'dark';
}

async function load() {
    error.value = '';
    try {
        const { data } = await window.axios.get(props.url, {
            responseType: 'text',
            transformResponse: [(d) => d],
        });
        const markdown = typeof data === 'string' ? data : String(data ?? '');

        viewer?.destroy();
        viewer = new Viewer({
            el: el.value,
            initialValue: markdown,
            theme: isDark() ? 'dark' : 'light',
        });
    } catch (e) {
        error.value = 'Could not render this file.';
    }
}

onMounted(load);
watch(() => props.url, load);
onBeforeUnmount(() => {
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
    max-height: 72vh;
    overflow: auto;
}
</style>
