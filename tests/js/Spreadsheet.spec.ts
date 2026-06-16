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
        ws, instance, cfg: { onselection: undefined as undefined | ((w: unknown, x: number, y: number) => void) },
        jss: vi.fn(),
        getArrayBuffer: vi.fn(() => Promise.resolve(new ArrayBuffer(8))),
        xlsxWrite: vi.fn(() => new Uint8Array([1, 2, 3, 4])),
    };
});

vi.mock('jspreadsheet-ce', () => ({
    default: (el: unknown, cfg: { onselection?: () => void }) => { h.cfg.onselection = cfg.onselection as never; return h.instance; },
}));
vi.mock('jspreadsheet-ce/dist/jspreadsheet.css', () => ({}));
vi.mock('jsuites/dist/jsuites.css', () => ({}));
vi.mock('@/lib/http', () => ({ getArrayBuffer: h.getArrayBuffer }));

const workbook = {
    SheetNames: ['Sheet1'],
    Sheets: {
        Sheet1: {
            '!ref': 'A1:B2',
            '!merges': [{ s: { r: 0, c: 0 }, e: { r: 0, c: 1 } }],
            '0:0': { v: 1, w: '1', t: 'n' },
            '0:1': { f: 'A1+1' },
            '1:0': { v: 'x', t: 's' },
        },
    },
};
vi.mock('xlsx', () => ({
    read: vi.fn(() => workbook),
    write: h.xlsxWrite,
    utils: {
        decode_range: vi.fn(() => ({ s: { r: 0, c: 0 }, e: { r: 1, c: 1 } })),
        encode_cell: vi.fn(({ r, c }: { r: number; c: number }) => `${r}:${c}`),
        encode_range: vi.fn(() => 'A1:B2'),
        book_new: vi.fn(() => ({ SheetNames: [], Sheets: {} })),
        book_append_sheet: vi.fn(),
        aoa_to_sheet: vi.fn(() => ({ '!ref': 'A1' })),
    },
}));

import SpreadsheetEditor from '@/Components/SpreadsheetEditor.vue';

beforeEach(() => { h.getArrayBuffer.mockClear(); h.xlsxWrite.mockClear(); h.ws.setValueFromCoords.mockClear(); });

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
        // Drive the jspreadsheet selection callback the editor registered.
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
        expect(h.xlsxWrite).toHaveBeenCalled();
        expect(blob).toBeInstanceOf(Blob);
    });

    it('creates a blank workbook when there is no url', async () => {
        const wrapper = mount(SpreadsheetEditor, { props: { url: '', type: 'xlsx' } });
        await flushPromises();
        expect(wrapper.emitted('ready')).toBeTruthy();
        expect(h.getArrayBuffer).not.toHaveBeenCalled();
    });
});
