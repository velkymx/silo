<script setup>
import { ref, watch, onMounted } from 'vue';

const props = defineProps({
    url: { type: String, required: true },
    type: { type: String, default: '' },
});

const container = ref(null);
const loading = ref(false);
const error = ref('');

const sheetTypes = ['xlsx', 'xls', 'csv', 'ods'];

async function render() {
    if (!container.value) return;
    container.value.innerHTML = '';
    error.value = '';
    loading.value = true;

    try {
        const res = await window.axios.get(props.url, { responseType: 'arraybuffer' });
        const buffer = res.data;

        if (props.type === 'docx') {
            const { renderAsync } = await import('docx-preview');
            await renderAsync(buffer, container.value, null, { inWrapper: true, className: 'docx' });
        } else if (sheetTypes.includes(props.type)) {
            const XLSX = await import('xlsx');
            const wb = XLSX.read(buffer, { type: 'array' });
            const wrap = document.createElement('div');
            wrap.className = 'table-responsive';
            wrap.innerHTML = XLSX.utils.sheet_to_html(wb.Sheets[wb.SheetNames[0]]);
            wrap.querySelector('table')?.classList.add('table', 'table-sm', 'table-bordered');
            container.value.appendChild(wrap);
        } else {
            error.value = 'No inline preview for this file type.';
        }
    } catch (e) {
        error.value = 'Could not render this document.';
    } finally {
        loading.value = false;
    }
}

onMounted(render);
watch(() => props.url, render);
</script>

<template>
    <div>
        <div v-if="loading" class="text-center text-muted py-5"><VibeSpinner class="me-2" />Rendering…</div>
        <div v-if="error" class="text-center text-muted py-5">{{ error }}</div>
        <div ref="container" class="doc-viewer" :style="{ maxHeight: '100%', overflow: 'auto' }"></div>
    </div>
</template>
