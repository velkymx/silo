<script setup>
import { onMounted, onBeforeUnmount, ref } from 'vue';
import jspreadsheet from 'jspreadsheet-ce';
import 'jspreadsheet-ce/dist/jspreadsheet.css';
import 'jsuites/dist/jsuites.css';

const props = defineProps({
    url: { type: String, required: true },
    type: { type: String, default: 'xlsx' },
});
const emit = defineEmits(['ready', 'error']);

const el = ref(null);
let instance = null;

const bookType = { xlsx: 'xlsx', xls: 'xls', csv: 'csv', ods: 'ods' };

async function load() {
    try {
        const res = await window.axios.get(props.url, { responseType: 'arraybuffer' });
        const XLSX = await import('xlsx');
        const wb = XLSX.read(res.data, { type: 'array' });

        const worksheets = wb.SheetNames.map((name) => {
            const aoa = XLSX.utils.sheet_to_json(wb.Sheets[name], { header: 1, defval: '' });
            return {
                worksheetName: name,
                data: aoa.length ? aoa : [['']],
                minDimensions: [Math.max(8, (aoa[0]?.length || 0)), Math.max(20, aoa.length)],
            };
        });

        instance = jspreadsheet(el.value, { worksheets, tabs: worksheets.length > 1 });
        emit('ready');
    } catch (e) {
        emit('error', 'Could not open this spreadsheet.');
    }
}

// Serialize the live grid back to a Blob of the original format.
async function serialize() {
    const XLSX = await import('xlsx');
    const wb = XLSX.utils.book_new();
    const sheets = instance?.worksheets ?? [instance];
    sheets.forEach((ws, i) => {
        const data = ws.getData();
        const sheet = XLSX.utils.aoa_to_sheet(data);
        const name = ws.options?.worksheetName || `Sheet${i + 1}`;
        XLSX.utils.book_append_sheet(wb, sheet, name.slice(0, 31));
    });
    const type = bookType[props.type] || 'xlsx';
    const out = XLSX.write(wb, { bookType: type, type: 'array' });
    return new Blob([out]);
}

defineExpose({ serialize });

onMounted(load);
onBeforeUnmount(() => {
    try {
        if (Array.isArray(instance)) instance.forEach((w) => w.destroy?.());
        else instance?.destroy?.();
    } catch (e) { /* noop */ }
    instance = null;
});
</script>

<template>
    <div ref="el" class="sheet-editor"></div>
</template>

<style>
.sheet-editor {
    overflow: auto;
    max-height: calc(100vh - 170px);
}

/* Force readable grid contrast regardless of app color mode. */
.sheet-editor table.jss_worksheet,
.sheet-editor .jss_worksheet td,
.sheet-editor .jss_worksheet th,
.sheet-editor .jexcel td,
.sheet-editor .jexcel th {
    color: #000 !important;
    background-color: #fff !important;
}
.sheet-editor .jss_worksheet thead td,
.sheet-editor .jss_worksheet > thead > tr > td,
.sheet-editor .jss_worksheet tbody > tr > td:first-child,
.sheet-editor .jexcel thead td,
.sheet-editor .jexcel tbody > tr > td.jss_row {
    color: #000 !important;
    background-color: #f3f3f3 !important;
}
.sheet-editor .jss_worksheet td.jss_selected,
.sheet-editor .jexcel td.highlight {
    background-color: #e8f0fe !important;
}
</style>
