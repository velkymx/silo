<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue';
import { getArrayBuffer } from '../lib/http';
import { sanitizeHtml } from '../lib/sanitize';

const props = defineProps({
    url: { type: String, required: true },
    type: { type: String, default: '' },
});

const container = ref(null);
const loading = ref(false);
const error = ref('');

const sheetTypes = ['xlsx', 'xls', 'csv', 'ods'];

// Race guard: paging through Quick Look changes `url` mid-render. Each render
// claims a sequence number; a stale render (older token) never paints.
let renderSeq = 0;

async function render() {
    if (!container.value) return;
    const token = ++renderSeq;
    container.value.innerHTML = '';
    error.value = '';
    loading.value = true;

    try {
        const buffer = await getArrayBuffer(props.url);
        if (token !== renderSeq) return;

        if (props.type === 'docx') {
            const { renderAsync } = await import('docx-preview');
            if (token !== renderSeq || !container.value) return;
            await renderAsync(buffer, container.value, null, { inWrapper: true, className: 'docx' });
        } else if (sheetTypes.includes(props.type)) {
            const XLSX = await import('xlsx');
            if (token !== renderSeq || !container.value) return;
            const wb = XLSX.read(buffer, { type: 'array' });
            const wrap = document.createElement('div');
            wrap.className = 'table-responsive';
            // sheet_to_html embeds user-controlled cell content — sanitize before innerHTML.
            wrap.innerHTML = sanitizeHtml(XLSX.utils.sheet_to_html(wb.Sheets[wb.SheetNames[0]]));
            wrap.querySelector('table')?.classList.add('table', 'table-sm', 'table-bordered');
            container.value.appendChild(wrap);
        } else {
            error.value = 'No inline preview for this file type.';
        }
    } catch (e) {
        if (token === renderSeq) error.value = 'Could not render this document.';
    } finally {
        if (token === renderSeq) loading.value = false;
    }
}

onMounted(render);
watch(() => props.url, render);
onBeforeUnmount(() => { renderSeq++; });
</script>

<template>
    <div>
        <div v-if="loading" class="text-center text-muted py-5"><VibeSpinner class="me-2" />Rendering…</div>
        <div v-if="error" class="text-center text-muted py-5">{{ error }}</div>
        <div ref="container" class="doc-viewer" :style="{ maxHeight: '100%', overflow: 'auto' }"></div>
    </div>
</template>
