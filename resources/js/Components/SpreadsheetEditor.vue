<script setup>
import { onMounted, onBeforeUnmount, ref } from 'vue';
import jspreadsheet from 'jspreadsheet-ce';
import 'jspreadsheet-ce/dist/jspreadsheet.css';
import 'jsuites/dist/jsuites.css';
import { getArrayBuffer } from '../lib/http';
import { sanitizeFormula } from '../lib/sanitizeFormula';

const props = defineProps({
    url: { type: String, required: true },
    type: { type: String, default: 'xlsx' },
});
const emit = defineEmits(['ready', 'error']);

const el = ref(null);
let instance = null;
let ExcelJS = null;
let originalWb = null;

// Formula bar state.
const cellRef = ref('');
const cellValue = ref('');
let activeWs = null;
let activeX = null;
let activeY = null;

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
    activeWs.setValueFromCoords(activeX, activeY, sanitizeFormula(cellValue.value), false);
}

// Parse "A1" style cell reference → 0-indexed { r, c }.
function parseCell(ref) {
    const m = String(ref).match(/^([A-Z]+)(\d+)$/i);
    if (!m) return { r: 0, c: 0 };
    let col = 0;
    for (const ch of m[1].toUpperCase()) col = col * 26 + ch.charCodeAt(0) - 64;
    return { r: parseInt(m[2]) - 1, c: col - 1 };
}

// What a cell shows in the grid: its formula, else its formatted text, else its raw value.
function cellDisplay(cell) {
    if (!cell || cell.value === null || cell.value === undefined) return '';
    const v = cell.value;
    if (v && typeof v === 'object' && v.formula) return `=${v.formula}`;
    if (v && typeof v === 'object' && v.sharedFormula) return `=${v.sharedFormula}`;
    return cell.text ?? (v != null ? String(v) : '');
}

// Build a formula-aware array-of-arrays from an ExcelJS worksheet.
function sheetToAoa(ws) {
    let maxCol = 1;
    ws.eachRow({ includeEmpty: true }, row => {
        if (row.cellCount > maxCol) maxCol = row.cellCount;
    });
    const aoa = [];
    ws.eachRow({ includeEmpty: true }, row => {
        const rowData = [];
        for (let c = 1; c <= maxCol; c++) {
            rowData.push(cellDisplay(row.getCell(c)));
        }
        aoa.push(rowData);
    });
    return aoa.length ? aoa : [['']];
}

// Convert ExcelJS worksheet merges into jspreadsheet's mergeCells map.
function mergeMap(ws) {
    const merges = ws.model?.merges;
    if (!merges?.length) return undefined;
    const map = {};
    merges.forEach(range => {
        const [startStr, endStr] = range.split(':');
        if (!endStr) return;
        const start = parseCell(startStr);
        const end = parseCell(endStr);
        map[startStr.toUpperCase()] = [end.c - start.c + 1, end.r - start.r + 1];
    });
    return map;
}

// Simple CSV parser for loading .csv files into an ExcelJS workbook.
function csvToWorkbook(text) {
    const wb = new ExcelJS.Workbook();
    const ws = wb.addWorksheet('Sheet1');
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
            ws.addRow(row); row = []; field = '';
        } else if (ch !== '\r') {
            field += ch;
        }
    }
    if (field || row.length) { row.push(field); ws.addRow(row); }
    return wb;
}

async function load() {
    try {
        const mod = await import('exceljs');
        ExcelJS = mod.default ?? mod;

        let wb;
        if (props.url) {
            const bytes = await getArrayBuffer(props.url);
            if (props.type === 'csv') {
                const text = new TextDecoder().decode(new Uint8Array(bytes));
                wb = csvToWorkbook(text);
            } else {
                // xls and ods are not supported by exceljs — surface a clear error.
                if (props.type === 'xls' || props.type === 'ods') {
                    emit('error', `Editing .${props.type} files is not supported. Download the file, convert it to .xlsx, and re-upload.`);
                    return;
                }
                wb = new ExcelJS.Workbook();
                await wb.xlsx.load(bytes);
            }
        } else {
            // New blank document.
            wb = new ExcelJS.Workbook();
            const ws = wb.addWorksheet('Sheet1');
            ws.addRow(['']);
        }
        originalWb = wb;

        const worksheets = wb.worksheets.map(ws => {
            const aoa = sheetToAoa(ws);
            return {
                worksheetName: ws.name,
                data: aoa,
                minDimensions: [Math.max(12, aoa[0]?.length || 0), Math.max(40, aoa.length)],
                mergeCells: mergeMap(ws),
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

// Serialize jspreadsheet edits back into the ExcelJS workbook and export as Blob.
async function serialize() {
    if (!originalWb || !instance) throw new Error('Workbook not loaded');

    if (props.type === 'csv') {
        // For CSV, build the output directly from jspreadsheet data.
        const sheets = instance.worksheets ?? [instance];
        const raw = sheets[0]?.getData(false) ?? [];
        const lines = raw.map(row =>
            row.map(v => {
                const s = v == null ? '' : String(v);
                return s.includes(',') || s.includes('"') || s.includes('\n')
                    ? `"${s.replace(/"/g, '""')}"`
                    : s;
            }).join(',')
        );
        return new Blob([new TextEncoder().encode(lines.join('\n'))], { type: 'text/csv' });
    }

    // For xlsx: patch the ExcelJS workbook with edited cell values, then write.
    const sheets = instance.worksheets ?? [instance];
    sheets.forEach((jss, i) => {
        const ws = originalWb.worksheets[i];
        if (!ws) return;
        const raw = jss.getData(false);
        raw.forEach((row, r) => {
            row.forEach((val, c) => {
                const cell = ws.getCell(r + 1, c + 1);
                const clean = sanitizeFormula(val);
                if (clean === '' || clean === null || clean === undefined) {
                    cell.value = null;
                } else if (typeof clean === 'string' && clean.startsWith('=')) {
                    cell.value = { formula: clean.slice(1) };
                } else if (typeof clean === 'string' && clean.trim() !== '' && !Number.isNaN(Number(clean))) {
                    cell.value = Number(clean);
                } else {
                    cell.value = String(clean);
                }
            });
        });
    });

    const buffer = await originalWb.xlsx.writeBuffer();
    return new Blob([buffer], { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' });
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
        <!-- Force the grid onto the light Bootstrap theme: the jspreadsheet
             engine renders on a white canvas and doesn't track dark mode. -->
        <div ref="el" class="sheet-editor" data-bs-theme="light"></div>
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
