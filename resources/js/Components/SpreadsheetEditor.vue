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
// The workbook exactly as read, kept so save patches it in place (preserving
// number formats, styles, merged cells, column widths) instead of rebuilding.
let originalWb = null;

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
            row.push(cellDisplay(ws[XLSX.utils.encode_cell({ r, c })]));
        }
        aoa.push(row);
    }
    return aoa.length ? aoa : [['']];
}

// What a cell shows in the grid: its formula, else its formatted text (so
// dates/currency read naturally), else its raw value.
function cellDisplay(cell) {
    if (!cell) return '';
    if (cell.f) return `=${cell.f}`;
    if (cell.w != null) return cell.w;
    return cell.v ?? '';
}

// Convert a sheet's "!merges" into jspreadsheet's mergeCells map so merged
// regions render and round-trip.
function mergeMap(ws) {
    const merges = ws['!merges'];
    if (!merges?.length) return undefined;
    const map = {};
    merges.forEach((m) => {
        const ref = XLSX.utils.encode_cell({ r: m.s.r, c: m.s.c });
        map[ref] = [m.e.c - m.s.c + 1, m.e.r - m.s.r + 1];
    });
    return map;
}

async function load() {
    try {
        XLSX = await import('xlsx');
        let wb;
        if (props.url) {
            const res = await window.axios.get(props.url, { responseType: 'arraybuffer' });
            wb = XLSX.read(res.data, { type: 'array', cellFormula: true, cellStyles: true });
        } else {
            // New blank document.
            wb = XLSX.utils.book_new();
            XLSX.utils.book_append_sheet(wb, XLSX.utils.aoa_to_sheet([['']]), 'Sheet1');
        }
        originalWb = wb;

        const worksheets = wb.SheetNames.map((name) => {
            const aoa = sheetToAoa(wb.Sheets[name]);
            return {
                worksheetName: name,
                data: aoa,
                minDimensions: [Math.max(12, aoa[0]?.length || 0), Math.max(40, aoa.length)],
                mergeCells: mergeMap(wb.Sheets[name]),
                // Layout
                tableOverflow: true,
                tableHeight: 'calc(100vh - 260px)',
                tableWidth: '100%',
                defaultColWidth: 120,
                // Every editing/interaction feature jspreadsheet-ce offers, on.
                search: true,
                filters: true,
                columnSorting: true,
                columnDrag: true,
                columnResize: true,
                rowResize: true,
                rowDrag: true,
                allowInsertRow: true,
                allowInsertColumn: true,
                allowDeleteRow: true,
                allowDeleteColumn: true,
                allowManualInsertRow: true,
                allowManualInsertColumn: true,
                allowRenameWorksheet: true,
                allowComments: true,
                wordWrap: true,
                selectionCopy: true,
                autoCasting: true,
                parseFormulas: true,
                autoIncrement: true,
                includeHeadersOnDownload: true,
            };
        });

        instance = jspreadsheet(el.value, {
            worksheets,
            tabs: true,
            toolbar: true,
            onselection: onSelection,
        });
        emit('ready');
    } catch (e) {
        emit('error', 'Could not open this spreadsheet.');
    }
}

// Apply one grid value onto a cell, preserving its number format (z) and style
// (s). Untouched cells keep their original formula/value/format verbatim.
function patchCell(sheet, ref, val) {
    const orig = sheet[ref];
    const unchanged = cellDisplay(orig) === (val == null ? '' : val);
    if (unchanged) return;

    if (val === '' || val === null || val === undefined) {
        if (orig) { delete orig.v; delete orig.f; delete orig.w; orig.t = 'z'; }
        return;
    }
    const cell = orig || (sheet[ref] = {});
    delete cell.w; // stale formatted text
    if (typeof val === 'string' && val.startsWith('=')) {
        cell.f = val.slice(1);
        delete cell.v;
        if (cell.t !== 'n' && cell.t !== 's') cell.t = 'n';
    } else if (typeof val === 'number' || (typeof val === 'string' && val.trim() !== '' && !isNaN(val))) {
        cell.v = Number(val);
        delete cell.f;
        cell.t = 'n';
    } else {
        cell.v = String(val);
        delete cell.f;
        cell.t = 's';
    }
}

// Serialize back to a Blob by patching the ORIGINAL workbook in place, so
// number formats, styles, merged cells and column widths survive the edit.
async function serialize() {
    const sheets = instance?.worksheets ?? [instance];
    sheets.forEach((ws, i) => {
        const name = ws.options?.worksheetName || originalWb?.SheetNames?.[i];
        const sheet = (name && originalWb?.Sheets?.[name]) || (originalWb.Sheets[originalWb.SheetNames[i]] = {});
        const raw = ws.getData(false);

        let maxR = 0;
        let maxC = 0;
        raw.forEach((row, r) => {
            row.forEach((val, c) => {
                patchCell(sheet, XLSX.utils.encode_cell({ r, c }), val);
                if (sheet[XLSX.utils.encode_cell({ r, c })]) { maxR = Math.max(maxR, r); maxC = Math.max(maxC, c); }
            });
        });

        // Grow the range if the grid extended beyond the original bounds.
        const origRange = sheet['!ref'] ? XLSX.utils.decode_range(sheet['!ref']) : { s: { r: 0, c: 0 }, e: { r: 0, c: 0 } };
        sheet['!ref'] = XLSX.utils.encode_range({
            s: { r: 0, c: 0 },
            e: { r: Math.max(origRange.e.r, maxR), c: Math.max(origRange.e.c, maxC) },
        });
    });

    const type = bookType[props.type] || 'xlsx';
    return new Blob([XLSX.write(originalWb, { bookType: type, type: 'array', cellStyles: true })]);
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
