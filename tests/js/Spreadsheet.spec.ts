import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';

const h = vi.hoisted(() => {
    const ws = {
        getValueFromCoords: vi.fn(() => 42),
        setValueFromCoords: vi.fn(),
        options: { worksheetName: 'Sheet1' },
        getData: vi.fn(() => [['1', '=A1+1', 'x']]),
        destroy: vi.fn(),
    };
    const instance = { worksheets: [ws], destroy: vi.fn() };
    return {
        ws, instance,
        cfg: { onselection: undefined as undefined | ((w: unknown, x: number, y: number) => void) },
        jss: vi.fn(),
        getArrayBuffer: vi.fn(() => Promise.resolve(new ArrayBuffer(8))),
        xlsxLoad: vi.fn(() => Promise.resolve()),
        xlsxWriteBuffer: vi.fn(() => Promise.resolve(new Uint8Array([1, 2, 3, 4]))),
    };
});

vi.mock('jspreadsheet-ce', () => ({
    default: (el: unknown, cfg: { onselection?: () => void }) => {
        h.cfg.onselection = cfg.onselection as never;
        return h.instance;
    },
}));
vi.mock('jspreadsheet-ce/dist/jspreadsheet.css', () => ({}));
vi.mock('jsuites/dist/jsuites.css', () => ({}));
vi.mock('@/lib/http', () => ({ getArrayBuffer: h.getArrayBuffer }));

// Minimal ExcelJS workbook mock: one sheet with two data rows and one merge.
vi.mock('exceljs', () => {
    const sheet = {
        name: 'Sheet1',
        model: { merges: ['A1:B1'] },
        eachRow: (opts: unknown, cb: (row: { cellCount: number; getCell: (c: number) => { value: unknown; text: string; formula?: string } }, ri: number) => void) => {
            cb({ cellCount: 2, getCell: (c: number) => c === 1 ? { value: 1, text: '1' } : { value: { formula: 'A1+1' }, text: '2', formula: 'A1+1' } }, 1);
            cb({ cellCount: 1, getCell: () => ({ value: 'x', text: 'x' }) }, 2);
        },
        getCell: vi.fn((_r: number, _c: number) => ({ value: null as unknown, set value(_v: unknown) {} })),
        addRow: vi.fn(),
    };
    return {
        default: {
            Workbook: class {
                worksheets = [sheet];
                addWorksheet = vi.fn(() => sheet);
                xlsx = { load: h.xlsxLoad, writeBuffer: h.xlsxWriteBuffer };
            },
        },
    };
});

import SpreadsheetEditor from '@/Components/SpreadsheetEditor.vue';

beforeEach(() => {
    h.getArrayBuffer.mockClear();
    h.xlsxLoad.mockClear();
    h.xlsxWriteBuffer.mockClear();
    h.ws.setValueFromCoords.mockClear();
});

describe('SpreadsheetEditor', () => {
    it('loads a workbook and emits ready', async () => {
        const wrapper = mount(SpreadsheetEditor, { props: { url: '/raw/1', type: 'xlsx' } });
        await flushPromises();
        expect(h.getArrayBuffer).toHaveBeenCalledWith('/raw/1');
        expect(wrapper.emitted('ready')).toBeTruthy();
    });

    it('emits error when loading fails', async () => {
        h.getArrayBuffer.mockRejectedValueOnce(new Error('boom'));
        const wrapper = mount(SpreadsheetEditor, { props: { url: '/raw/1', type: 'xlsx' } });
        await flushPromises();
        expect(wrapper.emitted('error')).toBeTruthy();
    });

    it('syncs the formula bar on cell selection and commits edits', async () => {
        const wrapper = mount(SpreadsheetEditor, { props: { url: '/raw/1', type: 'xlsx' } });
        await flushPromises();
        h.cfg.onselection!(h.ws, 1, 2);
        await flushPromises();
        expect(wrapper.find('.cell-ref').text()).toBe('B3');
        const input = wrapper.find('.formula-input');
        await input.setValue('=SUM(A1:A2)');
        await input.trigger('keyup', { key: 'Enter' });
        expect(h.ws.setValueFromCoords).toHaveBeenCalledWith(1, 2, '=SUM(A1:A2)', false);
    });

    it('serialize() patches the workbook and returns a Blob', async () => {
        const wrapper = mount(SpreadsheetEditor, { props: { url: '/raw/1', type: 'xlsx' } });
        await flushPromises();
        const blob = await (wrapper.vm as unknown as { serialize: () => Promise<Blob> }).serialize();
        expect(h.xlsxWriteBuffer).toHaveBeenCalled();
        expect(blob).toBeInstanceOf(Blob);
    });

    it('creates a blank workbook when there is no url', async () => {
        const wrapper = mount(SpreadsheetEditor, { props: { url: '', type: 'xlsx' } });
        await flushPromises();
        expect(wrapper.emitted('ready')).toBeTruthy();
        expect(h.getArrayBuffer).not.toHaveBeenCalled();
    });
});
