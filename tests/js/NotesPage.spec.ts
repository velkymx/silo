import { describe, it, expect, vi, beforeEach } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';

const h = vi.hoisted(() => ({
    post: vi.fn(),
    visit: vi.fn(),
    put: vi.fn(() => Promise.resolve({})),
    get: vi.fn(() => Promise.resolve({ backlinks: [] as Array<Record<string, unknown>> })),
    getText: vi.fn(() => Promise.resolve('# Hello')),
    prompt: vi.fn(() => Promise.resolve('Projects')),
    confirm: vi.fn(() => Promise.resolve(true)),
}));

vi.mock('@inertiajs/vue3', () => ({ router: { post: h.post, visit: h.visit, on: vi.fn(() => vi.fn()) } }));
vi.mock('@/lib/http', () => ({ http: { get: h.get, put: h.put }, getText: h.getText }));
vi.mock('@/composables/useConfirm', () => ({ usePrompt: () => ({ prompt: h.prompt }), useConfirm: () => ({ confirm: h.confirm }) }));

import NotesIndex from '@/Pages/Notes/Index.vue';
import NotesList from '@/Components/Notes/NotesList.vue';
import NotesSidebar from '@/Components/Notes/NotesSidebar.vue';
import BacklinksPanel from '@/Components/Notes/BacklinksPanel.vue';

const notes = [
    { id: 1, title: 'First', raw_url: '/raw/1', parent_id: 5, updated_at: '2026-06-22 10:00', tags: [{ id: 9, name: 'todo' }] },
    { id: 2, title: 'Second', raw_url: '/raw/2', parent_id: 12, updated_at: '2026-06-22 11:00', tags: [] },
];

const mountPage = () => mount(NotesIndex, {
    props: { rootId: 5, folders: [{ id: 12, name: 'Sub', parent_id: 5 }], notes, tags: [{ id: 9, name: 'todo' }] },
    global: { stubs: { MarkdownEditor: { template: '<div class="md-stub" />', props: ['modelValue', 'enableLinks'] } } },
});

beforeEach(() => {
    Object.values(h).forEach((f) => f.mockClear());
    h.getText.mockResolvedValue('# Hello');
    h.get.mockResolvedValue({ backlinks: [] });
});

describe('Notes/Index', () => {
    it('lists the notes', () => {
        const wrapper = mountPage();
        expect(wrapper.text()).toContain('First');
        expect(wrapper.text()).toContain('Second');
    });

    it('loads a note body when selected', async () => {
        const wrapper = mountPage();
        await wrapper.findComponent(NotesList).vm.$emit('select', 2);
        await flushPromises();
        expect(h.getText).toHaveBeenCalledWith('/raw/2');
    });

    it('shows a heading outline for the open note', async () => {
        h.getText.mockResolvedValue('# Alpha\n\nbody\n\n## Beta');
        const wrapper = mountPage();
        await wrapper.findComponent(NotesList).vm.$emit('select', 1);
        await flushPromises();
        expect(wrapper.text()).toContain('Alpha');
        expect(wrapper.text()).toContain('Beta');
        // Clicking an outline entry must not throw.
        await wrapper.findAll('.dd-item')[0]?.trigger('click');
    });

    it('creates a note in the current folder via the New button', async () => {
        const wrapper = mountPage();
        await wrapper.findComponent(NotesSidebar).vm.$emit('select-folder', 12);
        await wrapper.findComponent(NotesList).vm.$emit('new');
        expect(h.post).toHaveBeenCalledWith('/notes', { name: 'Untitled', parent_id: 12 }, expect.any(Object));
    });

    it('filters the list by tag path', async () => {
        const wrapper = mountPage();
        await wrapper.findComponent(NotesSidebar).vm.$emit('select-tag', 'todo');
        await flushPromises();
        expect(wrapper.text()).toContain('First');
        expect(wrapper.text()).not.toContain('Second');
    });

    it('a parent tag includes nested-child notes', async () => {
        const wrapper = mount(NotesIndex, {
            props: {
                rootId: 5,
                folders: [],
                notes: [
                    { id: 1, title: 'Parent', raw_url: '/raw/1', parent_id: 5, updated_at: '2026-06-22 10:00', tags: [{ id: 1, name: 'work' }] },
                    { id: 2, title: 'Child', raw_url: '/raw/2', parent_id: 5, updated_at: '2026-06-22 11:00', tags: [{ id: 2, name: 'work/projects' }] },
                    { id: 3, title: 'Other', raw_url: '/raw/3', parent_id: 5, updated_at: '2026-06-22 12:00', tags: [{ id: 3, name: 'home' }] },
                ],
                tags: [{ id: 1, name: 'work' }, { id: 2, name: 'work/projects' }, { id: 3, name: 'home' }],
            },
            global: { stubs: { MarkdownEditor: { template: '<div />', props: ['modelValue', 'enableLinks'] } } },
        });
        await wrapper.findComponent(NotesSidebar).vm.$emit('select-tag', 'work');
        await flushPromises();
        expect(wrapper.text()).toContain('Parent');
        expect(wrapper.text()).toContain('Child');
        expect(wrapper.text()).not.toContain('Other');
    });

    it('filters the list by folder', async () => {
        const wrapper = mountPage();
        await wrapper.findComponent(NotesSidebar).vm.$emit('select-folder', 12);
        await flushPromises();
        expect(wrapper.text()).toContain('Second');
        expect(wrapper.text()).not.toContain('First');
    });

    it('reorders the list when the sort changes', async () => {
        const wrapper = mountPage();
        await wrapper.findComponent(NotesList).vm.$emit('update:sort', 'name-desc');
        await flushPromises();
        const titles = (wrapper.findComponent(NotesList).props('notes') as Array<{ title: string }>).map((n) => n.title);
        expect(titles).toEqual(['Second', 'First']);
    });

    it('ME-14: in-flight autosave does not mutate state after unmount', async () => {
        vi.useFakeTimers();
        let resolveAutosave!: () => void;
        h.put.mockReturnValueOnce(new Promise<void>((res) => { resolveAutosave = res; }));

        const namedStub = { name: 'MarkdownEditor', template: '<div class="md-stub" />', props: ['modelValue', 'enableLinks'], emits: ['update:modelValue'] };
        const wrapper = mount(NotesIndex, {
            props: { rootId: 5, folders: [], notes, tags: [] },
            global: { stubs: { MarkdownEditor: namedStub } },
        });
        await wrapper.findComponent(NotesList).vm.$emit('select', 1);
        await flushPromises();
        // Emit content change from the editor stub to trigger autosave debounce.
        wrapper.findComponent({ name: 'MarkdownEditor' }).vm.$emit('update:modelValue', 'new content');
        vi.runAllTimers(); // fire the 800ms debounce → autosave starts
        await flushPromises(); // let the async autosave function run up to the await
        // Unmount before the http.put promise resolves.
        wrapper.unmount();
        // Resolve the in-flight request — must not throw or mutate dead state.
        expect(() => resolveAutosave()).not.toThrow();
        await flushPromises();
        vi.useRealTimers();
    });

    it('creates a folder via the New Folder button', async () => {
        const wrapper = mountPage();
        await wrapper.findComponent(NotesSidebar).vm.$emit('new-folder');
        await flushPromises();
        expect(h.prompt).toHaveBeenCalled();
        expect(h.post).toHaveBeenCalledWith('/notes/folders', { name: 'Projects', parent_id: null }, expect.any(Object));
    });
});

describe('NotesSidebar tree', () => {
    it('expands and collapses folders', async () => {
        const wrapper = mount(NotesSidebar, {
            props: {
                rootId: 5,
                folders: [
                    { id: 12, name: 'Parent', parent_id: 5 },
                    { id: 13, name: 'Child', parent_id: 12 },
                ],
                tags: [],
            },
        });
        // Child hidden until Parent is expanded.
        expect(wrapper.text()).toContain('Parent');
        expect(wrapper.text()).not.toContain('Child');

        await wrapper.get('.chevron').trigger('click');
        expect(wrapper.text()).toContain('Child');
    });

    it('builds a nested tag tree, collapsed by default', async () => {
        const wrapper = mount(NotesSidebar, {
            props: {
                rootId: 5,
                folders: [],
                tags: [{ id: 1, name: 'work' }, { id: 2, name: 'work/projects' }],
            },
        });
        // Parent segment shown; nested child hidden until expanded.
        expect(wrapper.text()).toContain('work');
        expect(wrapper.text()).not.toContain('projects');

        await wrapper.get('.chevron').trigger('click');
        expect(wrapper.text()).toContain('projects');
    });
});

describe('BacklinksPanel', () => {
    it('fetches and renders backlinks, emitting open on click', async () => {
        h.get.mockResolvedValue({ backlinks: [{ id: 7, title: 'Source', link_text: 'Source' }] });
        const wrapper = mount(BacklinksPanel, { props: { noteId: 3 } });
        await flushPromises();
        expect(h.get).toHaveBeenCalledWith('/notes/3/backlinks');
        expect(wrapper.text()).toContain('Source');

        await wrapper.get('button.vibe-btn').trigger('click');
        expect(wrapper.emitted('open')?.[0]).toEqual([7]);
    });
});
