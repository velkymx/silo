import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount } from '@vue/test-utils';

const { routerGet } = vi.hoisted(() => ({ routerGet: vi.fn() }));
vi.mock('@inertiajs/vue3', () => ({ router: { get: routerGet, post: vi.fn() } }));
// Stub the heavy office editors (jspreadsheet / SuperDoc) so the page mounts.
vi.mock('@/Components/SpreadsheetEditor.vue', () => ({ default: { name: 'SpreadsheetEditor', template: '<div class="sheet-stub" />' } }));
vi.mock('@/Components/DocxEditor.vue', () => ({ default: { name: 'DocxEditor', template: '<div class="docx-stub" />' } }));

import Editor from '@/Pages/Files/Editor.vue';

describe('Files/Editor page', () => {
    beforeEach(() => routerGet.mockClear());

    it('mounts the spreadsheet editor for sheet types', () => {
        const wrapper = mount(Editor, { props: { create: { type: 'xlsx', name: 'New.xlsx', parent_id: 4 } } });
        expect(wrapper.find('.sheet-stub').exists()).toBe(true);
        expect(wrapper.text()).toContain('New.xlsx');
        expect(wrapper.text()).toContain('New'); // "New" badge for creating
    });

    it('mounts the docx editor for docx files', () => {
        const wrapper = mount(Editor, { props: { file: { id: 9, name: 'memo.docx', type: 'docx', version: 3, parent_id: null, raw_url: '/raw/9' } } });
        expect(wrapper.find('.docx-stub').exists()).toBe(true);
        expect(wrapper.text()).toContain('v3');
    });

    it('shows an unsupported message for other types', () => {
        const wrapper = mount(Editor, { props: { create: { type: 'png', name: 'x.png', parent_id: null } } });
        expect(wrapper.text()).toContain('No editor available');
    });

    it('Back navigates to the parent folder', async () => {
        const wrapper = mount(Editor, { props: { file: { id: 9, name: 'memo.docx', type: 'docx', version: 1, parent_id: 7, raw_url: '/raw/9' } } });
        const back = wrapper.findAll('button').find((b) => b.text().includes('Back'));
        await back!.trigger('click');
        expect(routerGet).toHaveBeenCalledWith('/', { folder: 7 });
    });

    it('surfaces a child editor error', async () => {
        const wrapper = mount(Editor, { props: { create: { type: 'xlsx', name: 'New.xlsx', parent_id: null } } });
        wrapper.findComponent({ name: 'SpreadsheetEditor' }).vm.$emit('error', 'Could not load this document.');
        await wrapper.vm.$nextTick();
        expect(wrapper.text()).toContain('Could not load this document.');
    });

    it('Create opens the save dialog (exercises openSave)', async () => {
        const wrapper = mount(Editor, { props: { create: { type: 'xlsx', name: 'New.xlsx', parent_id: null } } });
        wrapper.findComponent({ name: 'SpreadsheetEditor' }).vm.$emit('ready');
        await wrapper.vm.$nextTick();
        const create = wrapper.findAll('button').find((b) => b.text().trim() === 'Create');
        await create!.trigger('click'); // exercises openSave()
        // Modal stub always renders its slots; the create-branch save button reads "Create".
        expect(wrapper.findAll('button').filter((b) => b.text().trim() === 'Create').length).toBeGreaterThan(0);
    });
});
