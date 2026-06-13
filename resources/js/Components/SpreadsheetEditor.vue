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
let XLSX = null;

// Formula bar state.
const cellRef = ref('');
const cellValue = ref('');
let activeWs = null;
let activeX = null;
let activeY = null;

const bookType = { xlsx: 'xlsx', xls: 'xls', csv: 'csv', ods: 'ods' };

function colName(x) {
    let s = '';
    x += 1;
    while (x > 0) {
        const m = (x - 1) % 26;
        s = String.fromCharCode(65 + m) + s;
        x = Math.floor((x - 1) / 26);
    }
    return s;
}

// jspreadsheet selection event → sync the formula bar to the active cell.
function onSelection(worksheet, x1, y1) {
    activeWs = worksheet;
    activeX = x1;
    activeY = y1;
    cellRef.value = `${colName(x1)}${y1 + 1}`;
    try {
        const raw = worksheet.getValueFromCoords(x1, y1, false);
        cellValue.value = raw == null ? '' : String(raw);
    } catch (e) {
        cellValue.value = '';
    }
}

// Commit the formula bar back into the active cell (triggers recalculation).
function commitCell() {
    if (!activeWs || activeX == null) return;
    activeWs.setValueFromCoords(activeX, activeY, cellValue.value, false);
}

// Build a formula-aware array-of-arrays from a SheetJS worksheet.
// Formula cells become "=EXPR" strings so jspreadsheet's engine evaluates them.
function sheetToAoa(ws) {
    if (!ws['!ref']) return [['']];
    const range = XLSX.utils.decode_range(ws['!ref']);
    const aoa = [];
    for (let r = range.s.r; r <= range.e.r; r++) {
        const row = [];
        for (let c = range.s.c; c <= range.e.c; c++) {
            const cell = ws[XLSX.utils.encode_cell({ r, c })];
            if (!cell) { row.push(''); continue; }
            row.push(cell.f ? `=${cell.f}` : (cell.v ?? ''));
        }
        aoa.push(row);
    }
    return aoa.length ? aoa : [['']];
}

async function load() {
    try {
        const res = await window.axios.get(props.url, { responseType: 'arraybuffer' });
        XLSX = await import('xlsx');
        const wb = XLSX.read(res.data, { type: 'array', cellFormula: true });

        const worksheets = wb.SheetNames.map((name) => {
            const aoa = sheetToAoa(wb.Sheets[name]);
            return {
                worksheetName: name,
                data: aoa,
                minDimensions: [Math.max(12, aoa[0]?.length || 0), Math.max(40, aoa.length)],
                tableOverflow: true,
                tableHeight: 'calc(100vh - 215px)',
                tableWidth: '100%',
                defaultColWidth: 120,
            };
        });

        instance = jspreadsheet(el.value, {
            worksheets,
            tabs: worksheets.length > 1,
            onselection: onSelection,
        });
        emit('ready');
    } catch (e) {
        emit('error', 'Could not open this spreadsheet.');
    }
}

// Serialize the live grid back to a Blob, preserving formulas.
async function serialize() {
    const out = XLSX.utils.book_new();
    const sheets = instance?.worksheets ?? [instance];
    sheets.forEach((ws, i) => {
        const raw = ws.getData(false); // false = raw values incl. "=FORMULA" strings
        const sheet = {};
        let maxR = 0;
        let maxC = 0;
        raw.forEach((row, r) => {
            row.forEach((val, c) => {
                if (val === '' || val === null || val === undefined) return;
                const ref = XLSX.utils.encode_cell({ r, c });
                if (typeof val === 'string' && val.startsWith('=')) {
                    sheet[ref] = { t: 'n', f: val.slice(1) };
                } else if (typeof val === 'number' || (typeof val === 'string' && val !== '' && !isNaN(val))) {
                    sheet[ref] = { t: 'n', v: Number(val) };
                } else {
                    sheet[ref] = { t: 's', v: String(val) };
                }
                if (r > maxR) maxR = r;
                if (c > maxC) maxC = c;
            });
        });
        sheet['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: maxR, c: maxC } });
        const name = (ws.options?.worksheetName || `Sheet${i + 1}`).slice(0, 31);
        XLSX.utils.book_append_sheet(out, sheet, name);
    });
    const type = bookType[props.type] || 'xlsx';
    return new Blob([XLSX.write(out, { bookType: type, type: 'array' })]);
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
    <div class="sheet-wrap">
        <div class="formula-bar d-flex align-items-center gap-2 mb-1">
            <span class="cell-ref">{{ cellRef || '—' }}</span>
            <span class="fx text-muted">fx</span>
            <input
                v-model="cellValue"
                class="formula-input flex-grow-1"
                placeholder="Value or =FORMULA (e.g. =SUM(A1:A5))"
                @keyup.enter="commitCell"
                @blur="commitCell"
            >
        </div>
        <div ref="el" class="sheet-editor"></div>
    </div>
</template>

<style>
.sheet-wrap {
    width: 100%;
    height: 100%;
}
.formula-bar {
    background: var(--bs-body-bg);
    border: 1px solid var(--bs-border-color);
    border-radius: 0.375rem;
    padding: 0.25rem 0.5rem;
}
.formula-bar .cell-ref {
    min-width: 64px;
    font-family: var(--bs-font-monospace);
    font-weight: 600;
    text-align: center;
    border-right: 1px solid var(--bs-border-color);
    padding-right: 0.5rem;
}
.formula-bar .fx {
    font-style: italic;
    font-family: serif;
}
.formula-bar .formula-input {
    border: 0;
    outline: 0;
    background: transparent;
    color: var(--bs-body-color);
    font-family: var(--bs-font-monospace);
}
.sheet-editor {
    width: 100%;
    height: 100%;
}
.sheet-editor .jss_container,
.sheet-editor .jtabs,
.sheet-editor .jtabs-content {
    width: 100% !important;
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
