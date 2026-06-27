<script setup>
import { ref, watch, onMounted, onBeforeUnmount } from 'vue';
import { getArrayBuffer } from '../lib/http';

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

function parseCsv(text) {
    const rows = [];
    let inQuote = false;
    let field = '';
    let row = [];
    for (let i = 0; i < text.length; i++) {
        const ch = text[i];
        if (inQuote) {
            if (ch === '"' && text[i + 1] === '"') { field += '"'; i++; }
            else if (ch === '"') { inQuote = false; }
            else { field += ch; }
        } else if (ch === '"') {
            inQuote = true;
        } else if (ch === ',') {
            row.push(field); field = '';
        } else if (ch === '\n') {
            row.push(field); field = '';
            rows.push(row); row = [];
        } else if (ch !== '\r') {
            field += ch;
        }
    }
    if (field || row.length) { row.push(field); rows.push(row); }
    return rows;
}

function buildTable(rows) {
    const table = document.createElement('table');
    table.className = 'table table-sm table-bordered';
    rows.forEach(cols => {
        const tr = table.insertRow();
        cols.forEach(val => {
            const td = tr.insertCell();
            td.textContent = val != null ? String(val) : '';
        });
    });
    return table;
}

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
        } else if (props.type === 'csv') {
            if (token !== renderSeq || !container.value) return;
            const text = new TextDecoder().decode(new Uint8Array(buffer));
            const wrap = document.createElement('div');
            wrap.className = 'table-responsive';
            wrap.appendChild(buildTable(parseCsv(text)));
            container.value.appendChild(wrap);
        } else if (props.type === 'xlsx') {
            const mod = await import('exceljs');
            const ExcelJS = mod.default ?? mod;
            if (token !== renderSeq || !container.value) return;
            const wb = new ExcelJS.Workbook();
            await wb.xlsx.load(buffer);
            const ws = wb.worksheets[0];
            if (!ws) { error.value = 'Empty workbook.'; return; }
            const rows = [];
            ws.eachRow({ includeEmpty: false }, row => {
                const cols = [];
                for (let c = 1; c <= row.cellCount; c++) {
                    const cell = row.getCell(c);
                    cols.push(cell.text ?? (cell.value != null ? String(cell.value) : ''));
                }
                rows.push(cols);
            });
            const wrap = document.createElement('div');
            wrap.className = 'table-responsive';
            wrap.appendChild(buildTable(rows));
            container.value.appendChild(wrap);
        } else if (sheetTypes.includes(props.type)) {
            error.value = `Inline preview is not available for .${props.type}. Download the file and open it locally.`;
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
