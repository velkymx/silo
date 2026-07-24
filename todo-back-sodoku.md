# Backlog — TDD Execution Plan

> Untracked working file. Completed work lives in `changelog.md`.
> Every item is **test-first**: write the **Red** test, watch it fail, then make it pass with **Green**.
> Order top-to-bottom. Each item = one commit + its test. TEST TEST TEST.

---

## 🚦 Ship Gate — Must-fix for v1.0

- [ ] **FE-P0-11 · Admin user edit fails when user has no group.**
  - **Problem:** The admin edit form submits `group_id: null` when a user has no group, but `AdminController::update` requires `group_id` (`required|exists:groups,id`). The save returns 422 and appears to do nothing.
  - **Source:** `resources/js/Pages/Admin/Users/Edit.vue:16-17` + `app/Http/Controllers/AdminController.php:92`.
  - **Fix:** Make `group_id` nullable in the backend validation and persist `null` when absent:
    ```php
    'group_id' => 'nullable|exists:groups,id',
    ```
    ```php
    $user->group_id = $validated['group_id'] ?? null;
    ```

- [ ] **FE-P0-12 · Singleton confirm dialog overwrites pending resolver.**
  - **Problem:** `useConfirm.ts` stores a single module-level `resolver`. A second `confirm()`/`prompt()` before the first settles replaces `resolver`, so the first caller’s Promise hangs forever.
  - **Source:** `resources/js/composables/useConfirm.ts:45-82`.
  - **Fix:** Implement a FIFO queue + current-resolver pattern so each dialog resolves in order:
    ```ts
    const queue: Array<{ mode: 'confirm' | 'prompt'; options: ConfirmOptions | PromptOptions; resolve: (v: boolean | string | null) => void }> = [];
    let currentResolver: ((v: boolean | string | null) => void) | null = null;

    function openNext() {
        if (state.open || queue.length === 0) return;
        const next = queue.shift()!;
        currentResolver = next.resolve;
        state.mode = next.mode;
        state.title = next.options.title ?? (next.mode === 'prompt' ? 'Enter a value' : 'Please confirm');
        state.message = next.options.message;
        state.confirmLabel = next.options.confirmLabel ?? (next.mode === 'prompt' ? 'OK' : 'Confirm');
        state.cancelLabel = next.options.cancelLabel ?? 'Cancel';
        state.variant = next.options.variant ?? 'primary';
        state.inputValue = next.mode === 'prompt' ? ((next.options as PromptOptions).value ?? '') : '';
        state.placeholder = next.mode === 'prompt' ? ((next.options as PromptOptions).placeholder ?? '') : '';
        state.open = true;
    }

    function enqueue(mode: 'confirm' | 'prompt', options: ConfirmOptions | PromptOptions) {
        return new Promise<boolean | string | null>((resolve) => {
            queue.push({ mode, options, resolve: resolve as (v: boolean | string | null) => void });
            openNext();
        });
    }

    export const confirm = (options: ConfirmOptions) => enqueue('confirm', options) as Promise<boolean>;
    export const prompt = (options: PromptOptions) => enqueue('prompt', options) as Promise<string | null>;

    export function settle(value: boolean | string | null) {
        const fn = currentResolver;
        currentResolver = null;
        state.open = false;
        fn?.(value);
        setTimeout(openNext, 0);
    }
    ```

- [ ] **FE-P0-13 · Photo editor Save can permanently disable the Save button.**
  - **Problem:** `saveEdit()` sets `editorSaving = true` before verifying that `cropper.value.getResult()` returns a canvas and that `toBlob` produces a blob. If either fails, the flag never resets.
  - **Source:** `resources/js/Pages/Photos/Index.vue:193-207`.
  - **Fix:**
    ```vue
    <script setup>
    function saveEdit() {
        if (!cropper.value) return;
        const result = cropper.value.getResult();
        const canvas = result?.canvas;
        if (!canvas) return;
        editorSaving.value = true;
        canvas.toBlob((blob) => {
            if (!blob || !editorPhoto.value) {
                editorSaving.value = false;
                return;
            }
            const form = new FormData();
            form.append('file', new File([blob], editorPhoto.value.name));
            form.append('note', 'Photo edit');
            form.append('_method', 'put');
            router.post(`/files/${editorPhoto.value.id}/content`, form, {
                forceFormData: true,
                onSuccess: () => { editorOpen.value = false; lightboxOpen.value = false; },
                onFinish: () => { editorSaving.value = false; },
            });
        }, 'image/jpeg', 0.92);
    }
    </script>
    ```

- [ ] **FE-P0-14 · Duplicate `:key` values when folders and files share ids.**
  - **Problem:** `items` merges `props.folders` and `props.files`; both use database `id`s, so a folder and a file can share an `id`. Duplicate sibling keys break Vue patch reconciliation and can cause lost state or runtime errors.
  - **Source:** `resources/js/Pages/Files/Index.vue:134-137`, grid `:key="item.id"` at line 673, `VibeDataTable row-key="id"` at line 707.
  - **Fix:** Add a composite `_key` and use it everywhere:
    ```vue
    <script setup>
    const items = computed(() => [
        ...props.folders.map((f) => ({ ...f, is_dir: true, modified: f.updated_at, _sort: 0, _key: `dir-${f.id}` })),
        ...props.files.map((f) => ({ ...f, is_dir: false, modified: f.created_at, _sort: 1, _key: `file-${f.id}` })),
    ]);
    </script>
    <template>
      <VibeCol v-for="item in gridItems" :key="item._key" ...>
      <VibeDataTable ... row-key="_key" ... />
    </template>
    ```

- [ ] **FE-P0-15 · Notes autosave timer leaks after unmount.**
  - **Problem:** `saveTimer` and the suppress-save `setTimeout` are never cleared. After the component unmounts they still fire, run HTTP requests, and mutate dead state.
  - **Source:** `resources/js/Pages/Notes/Index.vue:30-31`, `80-108`.
  - **Fix:**
    ```vue
    <script setup>
    let saveTimer: ReturnType<typeof setTimeout> | null = null;
    let suppressTimer: ReturnType<typeof setTimeout> | null = null;

    onBeforeUnmount(() => {
        if (saveTimer) clearTimeout(saveTimer);
        if (suppressTimer) clearTimeout(suppressTimer);
    });
    </script>
    ```

- [ ] **FE-P0-16 · Notes `loadContent` has no race guard.**
  - **Problem:** Clicking notes rapidly can make an older fetch resolve last and overwrite the currently selected note.
  - **Source:** `resources/js/Pages/Notes/Index.vue:80-91`.
  - **Fix:** Add a sequence token and drop stale results:
    ```vue
    <script setup>
    let loadSeq = 0;
    async function loadContent(note) {
        suppressSave = true;
        saveState.value = 'idle';
        const token = ++loadSeq;
        try {
            const text = await getText(note.raw_url);
            if (token !== loadSeq) return;
            content.value = text;
        } catch {
            if (token !== loadSeq) return;
            content.value = '';
        } finally {
            if (suppressTimer) clearTimeout(suppressTimer);
            suppressTimer = setTimeout(() => { suppressSave = false; }, 0);
        }
    }
    </script>
    ```

- [ ] **FE-P0-17 · App entry eagerly bundles every page.**
  - **Problem:** `import.meta.glob('./Pages/**/*.vue', { eager: true })` loads every page into the initial chunk, defeating code-splitting. `pages[...]` can also be `undefined` and throw at runtime.
  - **Source:** `resources/js/app.js:14-18`.
  - **Fix:**
    ```js
    createInertiaApp({
        resolve: async (name) => {
            const pages = import.meta.glob('./Pages/**/*.vue');
            const match = pages[`./Pages/${name}.vue`];
            if (!match) throw new Error(`Page not found: ${name}`);
            const page = await match();
            return page.default;
        },
        setup({ el, App, props, plugin }) {
            createApp({ render: () => h(App, props) }).use(plugin).use(VibeUI).mount(el);
        },
    });
    ```

- [ ] **FE-P0-18 · Vault secret reveal timers leak on unmount.**
  - **Problem:** `timers` holds active `setTimeout` ids but they are never cleared if the component unmounts while secrets are revealed.
  - **Source:** `resources/js/Pages/Vault/Index.vue:27-54`.
  - **Fix:**
    ```vue
    <script setup>
    const timers: Record<number, ReturnType<typeof setTimeout>> = {};
    onBeforeUnmount(() => {
        Object.values(timers).forEach(clearTimeout);
    });
    </script>
    ```

- [ ] **FE-P0-19 · Vault uses native `alert()`.**
  - **Problem:** Browser `alert()` breaks the in-app dialog convention, blocks the main thread, and is unstyled.
  - **Source:** `resources/js/Pages/Vault/Index.vue:47`.
  - **Fix:** Add `revealError = ref('')` and render `<VibeAlert variant="danger" dismissible>` under the page header.

- [ ] **FE-P0-20 · Directory loads ALL users and filters client-side.**
  - **Problem:** The directory page receives every user and filters locally. A 1,000-user org ships a giant payload, and the search never hits the server filter the controller already supports.
  - **Source:** `resources/js/Pages/Directory/Index.vue:15-20` + `DirectoryController@index`.
  - **Fix:** Drive the server search and render `props.people` directly:
    ```vue
    <script setup>
    const search = ref(props.filters?.search ?? '');
    const department = ref(props.filters?.department ?? '');
    let t: ReturnType<typeof setTimeout> | null = null;
    watch([search, department], ([s, d]) => {
        if (t) clearTimeout(t);
        t = setTimeout(() => router.get('/directory',
            { search: s || undefined, department: d || undefined },
            { preserveState: true, preserveScroll: true, replace: true }), 250);
    });
    </script>
    ```

- [ ] **FE-P0-21 · No shared `<PageHeader>` primitive — every page rolls its own header.**
  - **Problem:** Page headers are inconsistent across the app: Files/Index uses only a breadcrumb, Notes/Bookmarks/Photos have no `h1` at all, Vault/Directory/Trash/Admin use raw `<h4 class="mb-3">` with an icon, Errors uses `display-5`. Power users can't build a spatial map and screen readers see a different document outline on every page.
  - **Source:** `resources/js/Pages/Files/Index.vue:599-604`, `Notes/Index.vue:148`, `Bookmarks/Index.vue:152-216`, `Photos/Index.vue:215-216`, `Vault/Index.vue:117-118`, `Directory/Index.vue:53`, `Trash/Index.vue:48`, `Admin/Users/Index.vue:30`, `Errors/Error.vue:30-33`.
  - **Fix:** Build a single primitive and adopt it in every Page:
    ```vue
    <!-- resources/js/Components/PageHeader.vue -->
    <script setup lang="ts">
    interface Crumb { text: string; href?: string; active?: boolean }
    interface Action { text?: string; icon?: string; variant?: string; onClick?: () => void; href?: string }
    defineProps<{ title: string; icon?: string; breadcrumbs?: Crumb[]; actions?: Action[] }>();
    const emit = defineEmits<{ crumb: [Crumb] }>();
    </script>
    <template>
        <header class="d-flex flex-wrap align-items-center gap-2 mb-3">
            <VibeBreadcrumb v-if="breadcrumbs?.length" :items="breadcrumbs" @item-click="emit('crumb', $event.item)" class="me-2" />
            <h1 v-else class="h4 mb-0 d-flex align-items-center gap-2">
                <VibeIcon v-if="icon" :icon="icon" class="text-primary" />{{ title }}
            </h1>
            <div v-if="actions?.length" class="ms-auto d-flex flex-wrap gap-2">
                <VibeButton v-for="(a, i) in actions" :key="i" :variant="a.variant ?? 'secondary'" :outline="a.variant !== 'primary'" @click="a.onClick">
                    <VibeIcon v-if="a.icon" :icon="a.icon" class="me-1" />{{ a.text }}
                </VibeButton>
            </div>
        </header>
    </template>
    ```
    Then in every page: `<PageHeader title="Bookmarks" icon="bookmark-fill" :actions="[…]" />`.

- [ ] **FE-P0-22 · `useSelection` is wired into only 2 of 6 listing surfaces.**
  - **Problem:** The composable exists, Photos and Files use it, but Notes, Bookmarks, Vault, and Directory have no multi-select at all. A power user with 200 bookmarks or 80 vault secrets has to delete them one at a time.
  - **Source:** `resources/js/composables/useSelection.ts`, `Files/Index.vue:234-237`, `Photos/Index.vue:48-63`, absence in `Notes/Index.vue`, `Bookmarks/Index.vue`, `Vault/Index.vue`, `Directory/Index.vue`.
  - **Fix:** Wire `useSelection` into the four missing surfaces with tailored bulk actions:
    ```vue
    <!-- resources/js/Pages/Bookmarks/Index.vue -->
    <script setup>
    const { selectMode, selectedIds, selectedItems, isSelected, toggleSel, clearSelection, onItemClick }
        = useSelection( computed(() => props.bookmarks), (b) => selectId(b) );
    async function bulkDelete() {
        if (!await confirm({ title: `Remove ${selectedItems.value.length} bookmarks`, message: 'Remove the selected bookmarks?', confirmLabel: 'Remove', variant: 'danger' })) return;
        for (const b of selectedItems.value) router.delete(`/bookmarks/${b.id}`, { preserveScroll: true });
        clearSelection();
    }
    </script>
    <template>
        <BatchActions :selected-items="selectedItems" :current-id="null" :destination-options="[]" @cleared="clearSelection" @done="clearSelection">
            <template #extra>
                <VibeButton variant="danger" size="sm" outline @click="bulkDelete"><VibeIcon icon="trash" class="me-1" />Remove</VibeButton>
            </template>
        </BatchActions>
    </template>
    ```
    Repeat for Notes (bulk tag/move/delete), Vault (copy/generate/delete), Directory (copy email, message user).

- [ ] **FE-P0-23 · `AppLayout` and `PageError` double-render the same flash alert.**
  - **Problem:** `AppLayout.vue:278-279` renders `VibeAlert` for `flash.success` / `flash.error`, and `PageError.vue:10-19` also reads `page.props.flash.error` (and every page mounts `<PageError />` inside it). A single server error produces two stacked danger alerts.
  - **Source:** `resources/js/Layouts/AppLayout.vue:278-279`, `resources/js/Components/PageError.vue:10-19`, every Page that mounts `<PageError />`.
  - **Fix:** Pick one source of truth — `PageError` (it already handles multi-error, dismissible, and re-shows on new errors). Remove the duplicate from `AppLayout`:
    ```vue
    <!-- resources/js/Layouts/AppLayout.vue, replace lines 278-279 -->
    <PageError />
    <div class="flex-grow-1">
        <slot />
    </div>
    ```
    And drop every page's local `<PageError />` (or have `PageError` be mounted once at the layout level by every authenticated page through `AppLayout`).

- [ ] **FE-P0-24 · Notes page re-implements the shell instead of using the new `ThreePane`.**
  - **Problem:** `Bookmarks/Index.vue` adopts the shared `ThreePane` shell but `Notes/Index.vue:149-218` still rolls its own flexbox layout. Two content surfaces, two layouts — the user sees a different chrome when switching between Notes and Bookmarks.
  - **Source:** `resources/js/Pages/Notes/Index.vue:149-218`, `resources/js/Components/ThreePane.vue`.
  - **Fix:** Adopt `ThreePane` for the Notes page:
    ```vue
    <!-- resources/js/Pages/Notes/Index.vue -->
    <template>
        <AppLayout>
            <ThreePane list-width="300px">
                <template #sidebar>
                    <NotesSidebar :folders="folders" :root-id="rootId" :tags="tags"
                        :active-tag="activeTag" :selected-folder="selectedFolder"
                        @select-folder="selectFolder" @select-tag="selectTag" @new-folder="newFolder" />
                </template>
                <template #list>
                    <NotesList :notes="filteredNotes" :selected-id="selectedId" :sort="sortOrder"
                        @select="selectNote" @new="newNote" @update:sort="sortOrder = $event" />
                </template>
                <template #detail>
                    <div class="notes-editor d-flex flex-column h-100">
                        <template v-if="selectedNote">
                            <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom flex-shrink-0">
                                <button type="button" class="btn btn-link p-0 fw-semibold text-truncate text-decoration-none text-body" @click="renameNote">
                                    {{ selectedNote.title }}<VibeIcon icon="pencil" class="ms-1 small text-muted" />
                                </button>
                                <div class="d-flex align-items-center gap-2">
                                    <small class="text-muted">
                                        <span v-if="saveState === 'saving'">Saving…</span>
                                        <span v-else-if="saveState === 'saved'">Saved</span>
                                    </small>
                                    <VibeButton size="sm" variant="secondary" outline @click="saveVersion"><VibeIcon icon="bookmark-plus" class="me-1" />Save version</VibeButton>
                                </div>
                            </div>
                            <div class="notes-editor-body flex-grow-1">
                                <MarkdownEditor ref="editorRef" v-model="content" enable-links />
                            </div>
                            <BacklinksPanel :note-id="selectedId" @open="selectNote" />
                        </template>
                        <div v-else class="d-flex flex-column align-items-center justify-content-center h-100 text-muted">
                            <VibeIcon icon="journal-text" class="fs-1 mb-2" />
                            <p class="mb-0">Select a note, or create a new one.</p>
                        </div>
                    </div>
                </template>
            </ThreePane>
        </AppLayout>
    </template>
    ```
    Also add an `isNotes` style block scoped to the editor column.

- [ ] **FE-P0-25 · Quick-Look / file-preview is implemented three times.**
  - **Problem:** `QuickLookModal.vue` handles Images, PDFs, Audio, Video, Markdown, Office; `SharedListing.vue:339-355` re-implements an image/PDF modal; `Photos/Index.vue:323-385` re-implements a lightbox with a hand-rolled carousel. Three previews, three behaviors, three sets of accessibility bugs.
  - **Source:** `resources/js/Components/QuickLookModal.vue`, `resources/js/Components/SharedListing.vue:339-355`, `resources/js/Pages/Photos/Index.vue:323-385`.
  - **Fix:** Promote `QuickLookModal` to the only preview surface and delete the duplicates:
    ```vue
    <!-- resources/js/Components/SharedListing.vue: replace previewOpen + previewFile + preview modal with: -->
    <QuickLookModal v-model="previewOpen" :file="previewFile" :index="0" :total="1" :menu="[]" @step="previewOpen = false" />
    ```
    For Photos, replace the in-modal `VibeCarousel` with `QuickLookModal` driven by the existing `useQuickLook` composable — reuse the same step / keyboard / hover-peek primitives.

- [ ] **FE-P0-26 · No `useToast` / undo system — destructive actions are unrecoverable.**
  - **Problem:** Quick-Look Delete (`Files/Index.vue:549-555`), Photo Delete (`Photos/Index.vue:151-155`), Vault Remove (`Vault/Index.vue:94-97`), Bookmark Remove (`Bookmarks/Index.vue:106-113`), and `batchDelete` paths all show a confirm dialog and then vanish. Modern UX requires a 5-second undo window — without it, a mis-click loses data permanently.
  - **Source:** `Files/Index.vue:549-555`, `Photos/Index.vue:151-155, 306-311`, `Vault/Index.vue:94-97`, `Bookmarks/Index.vue:106-113`, `BatchActions.vue:36-39`, `Trash/Index.vue:33-41`, `Admin/Groups/Index.vue:44-47`.
  - **Fix:** Add a global toast + undo host:
    ```ts
    // resources/js/composables/useToast.ts
    import { reactive } from 'vue';
    interface Toast { id: number; text: string; variant: 'success' | 'danger' | 'info'; undo?: () => void }
    const state = reactive<{ items: Toast[] }>({ items: [] });
    let seq = 0;
    function dismiss(id: number) { state.items = state.items.filter((t) => t.id !== id); }
    function push(text: string, opts: { variant?: Toast['variant']; undo?: () => void; ttl?: number } = {}) {
        const id = ++seq;
        const t: Toast = { id, text, variant: opts.variant ?? 'success', undo: opts.undo };
        state.items.push(t);
        setTimeout(() => dismiss(id), opts.ttl ?? 6000);
        return id;
    }
    export function useToast() { return { state, push, dismiss }; }
    ```
    ```vue
    <!-- resources/js/Layouts/AppLayout.vue, near the bottom -->
    <ToastHost :items="toast.state.items" @dismiss="toast.dismiss" @undo="(t) => { t.undo?.(); toast.dismiss(t.id); }" />
    ```
    Then `Files/Index.vue:destroy()`:
    ```ts
    async function destroy(item) {
        if (!await confirm({ title: 'Move to trash', message: msg, confirmLabel: 'Move to trash', variant: 'danger' })) return;
        const snapshot = { ...item };
        router.delete(`/delete/${item.id}`, {
            preserveScroll: true,
            onSuccess: () => toast.push(`Moved "${item.name}" to trash`, {
                variant: 'success',
                undo: () => router.post(`/files/${snapshot.id}/restore`, {}, { preserveScroll: true }),
            }),
        });
    }
    ```
    Repeat the pattern across Photos, Vault, Bookmarks, Trash, BatchActions.

- [ ] **FE-P0-27 · `AppLayout` sidebar + `ThreePane` collide — content pages render 4 columns.**
  - **Problem:** `AppLayout.vue:94-96` flips to "full-bleed" (no main padding) for `/notes` and `/bookmarks`, but the 250px sidebar is still rendered. With `ThreePane` adding 230 + 340 = 570px, the user sees sidebar + 3-pane (sidebar/list/detail) — 4 visible columns — on every content surface.
  - **Source:** `resources/js/Layouts/AppLayout.vue:94-96, 226-274`, `resources/js/Components/ThreePane.vue`.
  - **Fix:** Add a layout mode that collapses the AppLayout sidebar to an icon-rail for content surfaces, and render it through a slot the 3-pane surfaces can populate:
    ```vue
    <!-- resources/js/Layouts/AppLayout.vue, replace the isNotes special case -->
    <script setup>
    const collapsedSidebar = computed(() => path.value.startsWith('/notes') || path.value.startsWith('/bookmarks'));
    </script>
    <template>
        <div class="d-flex flex-column min-vh-100 bg-body-tertiary">
            <header>…</header>
            <div class="d-flex flex-grow-1" style="min-height: 0">
                <aside v-if="!collapsedSidebar && !isMobile && sidebarOpen" class="app-sidebar d-flex flex-column flex-shrink-0 border-end bg-body p-2" style="width: 250px">
                    <!-- existing sidebar nav -->
                </aside>
                <aside v-else-if="!isMobile && sidebarOpen" class="app-sidebar app-sidebar--rail d-flex flex-column flex-shrink-0 border-end bg-body p-1 align-items-center" style="width: 60px">
                    <!-- icon-only nav -->
                    <VibeNav vertical :items="baseNav.map((i) => ({ ...i, text: '' }))" @item-click="onNav">
                        <template #item="{ item }"><VibeIcon :icon="item.icon" class="fs-5" /></template>
                    </VibeNav>
                </aside>
                <main class="flex-grow-1 min-vw-0 bg-body d-flex flex-column" :class="collapsedSidebar ? 'p-0' : 'p-3 p-lg-4'">
                    <slot />
                </main>
            </div>
        </div>
    </template>
    ```

- [ ] **FE-P0-28 · No modal auto-focuses its first field — keyboard users start in the page below.**
  - **Problem:** Bookmarks add/edit, Vault add/edit, Files rename/transfer/tags/share/details, Photos upload/album, Admin/Groups create, Notes rename (via `prompt()`) — none programmatically focus the first form field when the modal opens. After tabbing to the trigger button and pressing Enter, focus stays on the button underneath the modal. Screen-reader and keyboard-only users must Tab through the entire page to reach the form. WCAG 2.4.3 (Focus Order) violated.
  - **Source:** `resources/js/Components/RenameModal.vue:196-213`, `Vault/Index.vue:163-186`, `Bookmarks/Index.vue:293-325`, `Files/Index.vue:781-812, 815-854`, `Photos/Index.vue:388-406`, `Admin/Groups/Index.vue:55-68`. The component-level primitive should live in VibeUI but is not confirmed.
  - **Fix:** Either patch `VibeModal` in `@velkymx/vibeui` to auto-focus the first focusable descendant on `open=true`, or wrap it locally:
    ```vue
    <!-- resources/js/Components/AppModal.vue -->
    <script setup>
    const open = defineModel<boolean>({ required: true });
    const props = defineProps<{ title?: string; size?: 'sm' | 'lg' | 'xl' | 'fullscreen'; centered?: boolean; hideFooter?: boolean; scrollable?: boolean }>();
    const root = ref<HTMLElement | null>(null);
    function focusFirst() {
        const el = root.value?.querySelector<HTMLElement>('input, select, textarea, button, [tabindex]:not([tabindex="-1"])');
        el?.focus();
    }
    watch(() => open.value, async (v) => {
        if (!v) return;
        await nextTick();
        focusFirst();
    });
    </script>
    <template>
        <VibeModal ref="root" v-model="open" v-bind="props">
            <slot />
            <template v-if="$slots.footer" #footer><slot name="footer" /></template>
        </VibeModal>
    </template>
    ```
    Replace every `<VibeModal v-model="…">` with `<AppModal v-model="…">` (or fix VibeUI at the source).

- [ ] **FE-P0-29 · `VibeFormGroup` does not wire `aria-describedby` from help/error to the inner input.**
  - **Problem:** Every form (`Auth/*.vue`, `Profile/Edit.vue`, `Admin/Users/Edit.vue`, `Bookmarks/Index.vue`, `Vault/Index.vue`, etc.) wraps an input in `<VibeFormGroup label="…" :error="form.errors.x" help-text="…">`. Unless the group component generates a unique `id` per help/error and passes it to the input as `aria-describedby`, screen readers do not announce the help text or error when the input is focused. WCAG 1.3.1 (Info & Relationships) and 3.3.1 (Error Identification) violated.
  - **Source:** All `<VibeFormGroup>` consumers (35+ surfaces).
  - **Fix:** Patch VibeUI or wrap:
    ```vue
    <!-- resources/js/Components/AppFormGroup.vue -->
    <script setup lang="ts">
    const props = defineProps<{ label: string; helpText?: string; error?: string | null | undefined }>();
    const uid = useId();
    const helpId = computed(() => props.helpText ? `${uid}-help` : null);
    const errorId = computed(() => props.error ? `${uid}-error` : null);
    const describedBy = computed(() => [helpId.value, errorId.value].filter(Boolean).join(' ') || null);
    provide('formGroupDescribedBy', describedBy);
    </script>
    <template>
        <label v-if="label" :for="uid" class="form-label">{{ label }}</label>
        <slot :described-by="describedBy" :input-id="uid" />
        <small v-if="helpText" :id="helpId" class="form-text text-muted d-block mt-1">{{ helpText }}</small>
        <small v-if="error" :id="errorId" class="invalid-feedback d-block" role="alert">{{ error }}</small>
    </template>
    ```
    Consumers either use a default slot and pass `aria-describedby` to the input, or use a scoped slot that destructures `{ inputId, describedBy }`.

- [ ] **FE-P0-30 · No form marks required fields with a visible indicator.**
  - **Problem:** Every form sets `required` on inputs (e.g. `Auth/Login.vue:20, 29`; `Auth/Register.vue:20, 29, 38, 46`; `Admin/Users/Edit.vue:40, 49`; `Bookmarks/Index.vue:295, 298`; `Vault/Index.vue:164, 166, 169`) but renders no `*` or "(required)" marker. Sighted users can't tell which fields are required. WCAG 3.3.2 (Labels or Instructions) violated.
  - **Source:** All forms.
  - **Fix:** Convention: **red `*` for required, "(optional)" for non-required.** Render in `AppFormGroup`:
    ```vue
    <!-- inside AppFormGroup.vue template -->
    <label :for="uid" class="form-label">
        {{ label }}
        <span v-if="required" class="text-danger ms-1" aria-hidden="true">*</span>
        <span v-else class="text-muted ms-1 small" aria-hidden="true">(optional)</span>
    </label>
    <span class="visually-hidden" v-if="required">required</span>
    ```
    Audit every form and add `required` prop to `AppFormGroup`. Optional fields (description, icon, notes) explicitly opt out.

- [ ] **FE-P0-31 · Form error messages are not announced — `role="alert"` / `aria-live` missing.**
  - **Problem:** `<VibeFormGroup :validation-message="form.errors.x">` renders the error in a Bootstrap-styled `<small>`, but the surrounding DOM has no `role="alert"` or `aria-live="polite"`. When `form.errors` populates after a failed submit, screen readers do not announce the error. WCAG 4.1.3 (Status Messages) violated.
  - **Source:** All form error regions.
  - **Fix:** Add `role="alert"` to the error `<small>` in `AppFormGroup` (see FE-P0-29). For top-of-form error summaries, wrap the whole summary in `<div role="alert" aria-live="polite" class="alert alert-danger">`.

- [ ] **FE-P0-32 · Top-of-form error summary is missing on every multi-field form.**
  - **Problem:** When a form has 4 validation errors, the user has to scroll to find each `<small class="invalid-feedback">`. WCAG 3.3.1 specifically recommends an error summary at the top of the form, with anchor links to each invalid field, so screen-reader and sighted users can navigate to every problem.
  - **Source:** All forms with `form.errors` (auth, profile, admin edit, bookmark, vault).
  - **Fix:** Add a shared `<FormErrorSummary :errors="form.errors" />` mounted above the first form field:
    ```vue
    <!-- resources/js/Components/FormErrorSummary.vue -->
    <script setup lang="ts">
    const props = defineProps<{ errors: Record<string, string> }>();
    const entries = computed(() => Object.entries(props.errors).filter(([, v]) => v));
    </script>
    <template>
        <div v-if="entries.length" role="alert" aria-live="polite" class="alert alert-danger">
            <strong>Please fix the following:</strong>
            <ul class="mb-0">
                <li v-for="[key, msg] in entries" :key="key">
                    <a :href="`#field-${key}`" @click.prevent="$emit('focus', key)">{{ msg }}</a>
                </li>
            </ul>
        </div>
    </template>
    ```
    Emit `focus` and let the page call `document.getElementById('field-' + key)?.focus()`.

- [ ] **FE-P0-33 · Disabled button labels fail WCAG 1.4.3 contrast (~2.8:1).**
  - **Problem:** Every `:disabled="form.processing"` VibeButton renders with `--bs-btn-color` faded to `--bs-secondary-color` (#6c757d) on `--bs-secondary-bg` (#e9ecef), giving roughly 2.8:1 contrast — below the 4.5:1 AA threshold for normal text. The "Saving…/Save" label is exactly the affordance a low-vision user needs to read.
  - **Source:** All `VibeButton :disabled` sites (every form save button + every row action during processing).
  - **Fix:** Either fix VibeUI's disabled token to `--bs-body-color` on `--bs-tertiary-bg` (~7:1), or wrap in app:
    ```css
    /* resources/css/buttons.css */
    .btn:disabled, .btn.disabled {
        color: var(--bs-body-color) !important;
        background-color: var(--bs-tertiary-bg) !important;
        border-color: var(--bs-border-color) !important;
        opacity: 1 !important; /* override the 0.65 alpha that drops contrast further */
    }
    ```
    Import in `resources/css/theme.css`. Audit with `axe-core` in CI on the rendered page.

- [ ] **FE-P0-34 · Placeholder text contrast < 3:1 (universal — fails WCAG 1.4.3).**
  - **Problem:** Every form `<VibeFormInput placeholder="…">` renders browser-default placeholder text at ~`#a1a8b0` (60% alpha on the input's text color). The contrast against a white input background is ~2.6:1, well below the 4.5:1 AA threshold. Sighted low-vision users can't read the hint ("https://…", "Untitled.md", "AWS root"). WCAG explicitly notes placeholders are not a substitute for labels but must be readable when used.
  - **Source:** Every form input across the app.
  - **Fix:** Add a global stylesheet that bumps placeholder contrast and ensures all forms have a real label (not placeholder-only):
    ```css
    /* resources/css/forms.css */
    ::placeholder { color: #6c757d !important; opacity: 1 !important; } /* 4.5:1 on white */
    ::-webkit-input-placeholder { color: #6c757d !important; opacity: 1 !important; }
    :-ms-input-placeholder { color: #6c757d !important; opacity: 1 !important; }
    ```
    Also: audit every consumer to ensure `<VibeFormInput placeholder="X">` is always wrapped in `<AppFormGroup label="X">` so the label, not the placeholder, carries the meaning.

- [ ] **FE-P0-35 · File and photo cards are keyboard-inaccessible click-only `<div>`s.**
  - **Problem:** Grid/list file items and photo grid cells rely on `<div @click>` for their primary action. Keyboard and screen-reader users cannot focus or activate them, blocking the core file-browsing task.
  - **Source:** `resources/js/Components/FileItem.vue`, `resources/js/Pages/Photos/Index.vue`.
  - **Fix:** Convert the primary hit area to a real `<button type="button">` with visible focus styles and keyboard handlers. Keep right-click context-menu support on the wrapper.

- [ ] **FE-P0-36 · Context menu has no focus trap or keyboard navigation.**
  - **Problem:** The right-click context menu is mouse-only. Focus stays on the trigger, `Escape` may not close the menu, and arrow/Home/End keys do nothing.
  - **Source:** `resources/js/Components/ContextMenu.vue`.
  - **Fix:** Move focus to the first menu item on open, implement roving tabindex or `aria-activedescendant`, and close on `Escape` or outside click while restoring focus to the trigger.

- [ ] **FE-P0-37 · Three-pane layouts are not responsive on mobile.**
  - **Problem:** Notes, Bookmarks, and Directory use fixed 230 px + 340 px columns. On narrow viewports the columns squish or overflow, making the app unusable on phones and small tablets.
  - **Source:** `resources/js/Components/ThreePane.vue`, `resources/js/Pages/Notes/Index.vue`, `resources/js/Pages/Bookmarks/Index.vue`.
  - **Fix:** Stack the sidebar/list/detail panes vertically below a breakpoint and use relative heights instead of fixed pixel widths.

- [ ] **FE-P0-38 · Files list table is marked non-responsive.**
  - **Problem:** `VibeDataTable` is rendered with `:responsive="false"`, so the file listing overflows horizontally on mobile without a scrollable container.
  - **Source:** `resources/js/Pages/Files/Index.vue`.
  - **Fix:** Remove `:responsive="false"` or wrap the table in a `.table-responsive` container so narrow screens can scroll horizontally.

- [ ] **FE-P0-39 · Playwright E2E infrastructure does not exist — modal a11y and bulk-select flows have no end-to-end coverage.**
  - **Problem:** `package.json` has no `@playwright/test`, no `playwright.config.{js,ts}`, no `tests/e2e/` directory. Every new feature ships without an E2E regression. The Lighthouse accessibility gate in the v1.0 readiness checklist relies on a one-shot manual run; the axe-core and bulk-select items have nothing to assert against. Modal a11y (FE-P0-28, FE-P0-29, FE-P0-30, FE-P0-32) cannot be verified without a real browser, a screen-reader assertion harness, and a Playwright trace.
  - **Source:** `package.json` (absent `@playwright/test`), `playwright.config.js` (existence, but only for the legacy pre-2026 `npm run test:e2e` script referenced in `changelog.md`), `tests/e2e/` (absent).
  - **Fix:** Adopt `@playwright/test` 1.49+, wire a real config, ship four baseline specs:
    ```ts
    // playwright.config.ts
    import { defineConfig, devices } from '@playwright/test';
    export default defineConfig({
        testDir: './tests/e2e',
        fullyParallel: true,
        forbidOnly: !!process.env.CI,
        retries: process.env.CI ? 2 : 0,
        reporter: process.env.CI ? [['html'], ['github']] : 'list',
        use: { baseURL: process.env.E2E_BASE_URL ?? 'http://127.0.0.1:8000', trace: 'on-first-retry' },
        projects: [
            { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
            { name: 'webkit', use: { ...devices['Desktop Safari'] } },
        ],
        webServer: process.env.CI ? undefined : {
            command: 'php artisan serve --port=8000 && npm run build',
            url: 'http://127.0.0.1:8000',
            reuseExistingServer: !process.env.CI,
            timeout: 120_000,
        },
    });
    ```
    ```ts
    // tests/e2e/a11y.spec.ts
    import { test, expect } from '@playwright/test';
    test('login form is accessible', async ({ page }) => {
        await page.goto('/login');
        await expect(page.getByLabel('Email')).toBeVisible();
        await expect(page.getByLabel('Password')).toBeVisible();
        await expect(page.getByRole('button', { name: /sign in/i })).toBeVisible();
        // The axe-core plugin is wired in QA-P1-01.
    });
    ```
    Baseline specs to ship: `a11y.spec.ts` (login + register), `files-crud.spec.ts` (upload → tag → delete → undo), `bulk-select.spec.ts` (Files + Notes + Bookmarks), `preview.spec.ts` (Quick Look keyboard nav).
    Add a separate `frontend-e2e` job to `.github/workflows/ci.yml` (the existing `frontend-ci` job runs typecheck + Vitest; this new job installs Playwright browsers, builds assets, and runs the four specs). The job may be **non-blocking** until QA-P1-01 lands; flip to required before v1.0.

---

## 🚧 IN PROGRESS

- [~] **P4. `Files/Index.vue` decomposition** — 1761 → ~1030 lines; remaining modals to extract (`NewFolderModal`, `TransferModal`, `TagsModal`, `DetailsModal`, `VersionsModal`, `BatchFolder/Move/Rename`, `QuickLook`). Touches as part of this: BE-P4-1 test coverage, FE-P1-12 submit-button spinner, FE-P2-2 File mutation, FE-P1-3 FolderTree a11y.

## Open Checklist

### REF-P1 — Architecture / DRY-up (foundation-first)

- [ ] **REF-P1-01 · Split FileController god object + FormRequests + Resources**
  - **Problem:** `FileController.php` is ~1,000 lines and mixes index, upload, edit, download, batch ops, versions, tags, thumbnails, and raw responses. Inline validation blocks are copied 15+ times and response shapes are hand-rolled in every controller.
  - **Source:** `app/Http/Controllers/FileController.php:22-998`; repeated `$request->validate([...])` blocks at `:135-139`, `:211-216`, `:286-290`, `:346-350`, etc.; `transform()`/`folderShape()` at `:507-554`.
  - **Fix (rip out, not migrate):**
    - Delete the monolith.
    - Create `FolderController`, `BatchController`, `VersionController`.
    - Create `StoreTextFileRequest`, `UploadFilesRequest`, `RenameItemRequest`, `MoveCopyRequest`.
    - Create `FileResource`, `FolderResource`, `VersionResource`.
    - Route each URL to the new controller; controllers return resources or Inertia data, never ad-hoc arrays.
  - **TDD atomic steps:**
    1. Red test: `StoreTextFileRequest` rejects missing `name`/`content`.
    2. Red test: `FileController@index` returns `FileResource` shape.
    3. Red test: `BatchController@batchMove` uses `MoveCopyRequest`.
    4. Implement requests/resources/controllers.
    5. Run `php artisan test`; fix regressions; commit.

- [ ] **REF-P1-02 · Generic `ResourceModal` component for add/edit flows**
  - **Problem:** Bookmarks, Vault, Photos albums, and Admin Groups each reimplement `editingId`, `form.reset()`, `form.clearErrors()`, and the `post/put` branch.
  - **Source:** `resources/js/Pages/Bookmarks/Index.vue:78-104`; `resources/js/Pages/Vault/Index.vue:61-87`; `resources/js/Pages/Admin/Groups/Index.vue:17-42`; `resources/js/Pages/Photos/Index.vue:165-169`.
  - **Fix (opinionated):**
    - Create `components/ResourceModal.vue` that owns `editingId`, `openNew(item?)`, `save()`, busy state, and error display.
    - Expose scoped slots for fields and a `fields` prop/config.
    - Pages supply only the `useForm` shape, store/update URLs, and field markup.
  - **TDD atomic steps:**
    1. Red component test: `ResourceModal` renders slot fields.
    2. Red test: submit calls `post` when `editingId` is null, `put` when set.
    3. Red test: cancel resets form and closes modal.
    4. Implement component; replace one page (Bookmarks) first.
    5. Replace Vault, Groups, Photos album modals; commit per page.

- [ ] **REF-P1-03 · `useUrlFilter` composable for listing-page search/filter sync**
  - **Problem:** Files, Photos, Bookmarks, and Directory each hand-roll `watch`, `setTimeout`, `router.get`, `preserveState`, `replace`, and query cleanup.
  - **Source:** `resources/js/Pages/Files/Index.vue:83-101`; `resources/js/Pages/Bookmarks/Index.vue:18-26`; `resources/js/Pages/Directory/Index.vue:12-20`; `resources/js/Layouts/AppLayout.vue:30-56`.
  - **Fix (opinionated):**
    - Create `composables/useUrlFilter.ts` accepting `{ basePath, initialFilters, debounceMs }`.
    - Returns `filters`, `setFilter(key, value)`, `clearFilters()`, `isDirty`, and handles timer cleanup automatically.
    - Pages call `setFilter('search', value)` instead of `router.get`.
  - **TDD atomic steps:**
    1. Red unit test: `useUrlFilter` debounces and calls `router.get` with cleaned query.
    2. Red test: `clearFilters` removes keys and visits base path.
    3. Implement composable; migrate Bookmarks first.
    4. Migrate Directory, Photos, Files; commit per migration.

- [ ] **REF-P1-04 · Single source of truth for file categories, statuses, and formatters**
  - **Problem:** File-type lists, status strings, and byte formatting are duplicated and drifting between JS components and PHP controllers/services.
  - **Source:** `resources/js/lib/format.ts` exists but is ignored by inline `size / 1024` in `FileItem.vue:81`, `Files/Index.vue:366/742/836`, `QuickLookModal.vue:134`; type lists repeated in `lib/fileTypes.ts`, `FileSearch.php`, `PhotoController.php`, `QuickLookModal.vue`, `Files/Editor.vue`; status strings hardcoded in `FileItem.vue`, `useJobPolling.ts`, `Backups.vue`.
  - **Fix (rip out):**
    - Delete inline KB formatters; use `fmtBytes()` everywhere.
    - Create `resources/js/lib/constants.ts` with `FileStatus`, `BackupStatus`, `BookmarkStatus`, and `FILE_CATEGORIES` map.
    - Mirror the map in PHP (`config/file_categories.php` or enum) and use it in `FileSearch`, `PhotoController`, and resource transformers.
    - Expose constants via `HandleInertiaRequests::share` so JS cannot drift from backend.
  - **TDD atomic steps:**
    1. Red test: `fmtBytes` replaces all inline size formatting (component snapshots).
    2. Red test: `FILE_CATEGORIES` correctly derives icon/preview/editor flags.
    3. Red feature test: backend search uses PHP category map.
    4. Implement constants/config; delete duplicated arrays.

- [ ] **REF-P1-05 · Centralise authorization via Policies + `EnsureAdmin` middleware + test helpers**
  - **Problem:** Ownership/admin checks are inline and repeated across controllers and feature tests.
  - **Source:** `app/Http/Controllers/AdminController.php:13-27`; `GroupController.php:11-19`; `AuditController.php:11-18`; `PhotoController.php:169-172`; `SavedSearchController.php:41-44`; `BookmarkController.php:258`; `VaultController.php:164`; duplicated `User::factory()->create()` + `actingAs` in many Feature tests.
  - **Fix (rip out):**
    - Replace admin closure middleware with `Route::middleware('admin')->group(...)` or controller `$this->middleware('admin')`.
    - Create `AlbumPolicy`, `SavedSearchPolicy`, `BookmarkPolicy`, `VaultItemPolicy`.
    - Replace inline `owner_id === auth()->id()` with `$this->authorize('update', $model)` and `Gate::check('update', $model)` in resources.
    - Add `asUser()`, `withFile()`, `withFolder()` helpers to `Tests\TestCase`.
  - **TDD atomic steps:**
    1. Red feature test: admin routes reject non-admin via middleware.
    2. Red feature test: `BookmarkPolicy@update` rejects non-owner, allows owner/admin.
    3. Red test: `asUser()` and `withFile()` helpers work.
    4. Implement policies/middleware/helpers; replace inline checks.
    5. Run `php artisan test`; commit.

- [ ] **REF-P1-06 · `App*` shim layer wrapping VibeUI until upstream fixes ship.**
  - **Problem:** 12 VibeUI defects (see `VIBEUI-ISSUES.md`) block v1.0 form / modal / a11y work. We do not own `@velkymx/vibeui` upstream; the team will file issues but the fixes may not land in time. Every consumer currently uses `<VibeModal>`, `<VibeFormGroup>`, `<VibeButton>`, `<VibeFormInput>` directly. A breaking upstream change before v1.0 would force a re-implementation across 35+ files.
  - **Source:** Every component that uses VibeUI primitives (grep for `VibeModal`, `VibeFormGroup`, `VibeButton`, `VibeFormInput` in `resources/js/`).
  - **Fix:** Create a thin shim layer in `resources/js/Components/` that **fixes the defects locally** and proxies the rest to VibeUI. Pages import the shim, never the VibeUI primitive directly. When upstream lands, the shim delegates to it and we delete the override.
    ```ts
    // resources/js/Components/AppModal.vue
    <script setup lang="ts">
    /**
     * Shim over <VibeModal> that fixes the upstream a11y defects documented in
     * VIBEUI-ISSUES.md (#1 auto-focus, #2 inert/focus-trap, #3 Cmd+Enter submit).
     * Once @velkymx/vibeui ships the fix, delete the override in this file —
     * the public API of <AppModal> stays identical so no consumer changes.
     */
    import { ref, watch, nextTick, useSlots } from 'vue';
    import VibeModal from '@velkymx/vibeui/components/VibeModal.vue';
    const open = defineModel<boolean>({ required: true });
    defineProps<{ title?: string; size?: 'sm' | 'lg' | 'xl' | 'fullscreen'; centered?: boolean; hideFooter?: boolean; scrollable?: boolean }>();
    const root = ref<HTMLElement | null>(null);
    function focusFirst() {
        root.value?.querySelector<HTMLElement>('input, select, textarea, button, [tabindex]:not([tabindex="-1"])')?.focus();
    }
    watch(() => open.value, async (v) => { if (v) { await nextTick(); focusFirst(); } });
    function onKeydown(e: KeyboardEvent) {
        if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
            // Cmd/Ctrl+Enter inside any modal that wraps a <form> submits it.
            const form = root.value?.querySelector<HTMLFormElement>('form');
            form?.requestSubmit();
        }
    }
    </script>
    <template>
        <VibeModal ref="root" v-model="open" v-bind="$attrs" @keydown="onKeydown">
            <slot />
            <template v-if="$slots.footer" #footer><slot name="footer" /></template>
        </VibeModal>
    </template>
    ```
    Build equivalents: `AppFormGroup.vue` (wired `id`/`for`/`aria-describedby`, required indicator, `role="alert"` error), `AppButton.vue` (disabled-state contrast, required `aria-label` when icon-only), `AppInput.vue` (placeholder contrast, optional `show-password` and `password-strength` slots, `autocomplete` pass-through).
    Migration is a **single sed pass** per primitive: `s/<VibeModal/<AppModal/g`, `s/<VibeFormGroup/<AppFormGroup/g`, etc. across `resources/js/`. Then `package.json` aliases `@/Components/App*` as the canonical import path so we never reach for VibeUI directly.
  - **TDD atomic steps:**
    1. Red unit test (`vitest` + `@vue/test-utils` + `jsdom`): mount `AppModal`, open it, assert `document.activeElement` is the first input.
    2. Red test: open `AppModal`, press `Cmd+Enter`, assert the inner `<form>` was submitted.
    3. Red test: open `AppFormGroup` with a `helpText` and `error`, assert the input's `aria-describedby` references both elements.
    4. Implement shims; replace consumers one section at a time (Files → Notes → Bookmarks → Photos → Vault → Admin).
    5. Run `npm run typecheck && npm run test:unit`; commit per primitive.

### FE-P1 — High Friction

- [ ] **FE-P1-16 · MarkdownEditor autocomplete timer leaks after unmount.**
  - **Problem:** `fetchTimer` is never cleared, so aborted fetches and delayed searches continue after the editor is gone.
  - **Source:** `resources/js/components/MarkdownEditor.vue:82-106`.
  - **Fix:**
    ```vue
    <script setup>
    let fetchTimer: ReturnType<typeof setTimeout> | null = null;
    onBeforeUnmount(() => {
        if (fetchTimer) clearTimeout(fetchTimer);
    });
    </script>
    ```

- [ ] **FE-P1-17 · EditorModal content has no load race guard.**
  - **Problem:** Quickly toggling the editor can leave the wrong file content on screen when an earlier fetch resolves last.
  - **Source:** `resources/js/components/EditorModal.vue:70-92`.
  - **Fix:** Add a sequence counter and skip stale results, mirroring Notes fix.

- [ ] **FE-P1-18 · Directory profile load race.**
  - **Problem:** `showProfile` starts an async `fetch` without cancelling/ignoring stale results. Fast hovering/clicks can paint the wrong profile.
  - **Source:** `resources/js/Pages/Directory/Index.vue:40-60`.
  - **Fix:** Track the requested user id and only update `profile.value` if it still matches.

- [ ] **FE-P1-19 · BacklinksPanel load race.**
  - **Problem:** `loadBacklinks` fetches asynchronously and assigns state without a sequence guard; stale responses can overwrite newer ones.
  - **Source:** `resources/js/Pages/Admin/Backlinks/Index.vue:50-60`.
  - **Fix:** Store a `loadSeq` and drop results whose sequence does not match the latest request.

- [ ] **FE-P1-20 · ShareModal load + mutation races.**
  - **Problem:** `loadUsers`, `loadGroups`, and save handlers mutate reactive arrays without serialising calls. Interleaving operations can leave stale selected state or duplicate entries.
  - **Source:** `resources/js/components/ShareModal.vue:50-125`.
  - **Fix:** Use a single `refresh()` async flow and disable form controls while `saving`.

- [ ] **FE-P1-21 · Profile avatar cropper lacks save guard.**
  - **Problem:** `saveAvatar()` calls `cropper.getResult().canvas.toBlob()` without checking that the cropper instance still exists or that the canvas was produced. A failed crop disables the button.
  - **Source:** `resources/js/Pages/Profile/Partials/UpdateProfileInformationForm.vue:75-95`.
  - **Fix:**
    ```vue
    <script setup>
    async function saveAvatar() {
        if (!cropper.value) return;
        const canvas = cropper.value.getResult()?.canvas;
        if (!canvas) return;
        savingAvatar.value = true;
        const blob = await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, 'image/png'));
        if (!blob) { savingAvatar.value = false; return; }
        router.post('/profile/avatar', { avatar: new File([blob], 'avatar.png') }, {
            onFinish: () => { savingAvatar.value = false; },
        });
    }
    </script>
    ```

- [ ] **FE-P1-22 · Vault generate action has no error handling.**
  - **Problem:** `generate()` only sets success values; if crypto/clipboard fails it leaves stale state and the UI gives no feedback.
  - **Source:** `resources/js/Pages/Vault/Index.vue:55-75`.
  - **Fix:** Wrap in `try/catch`, show `revealError`, and reset `generatedSecret` on failure.

- [ ] **FE-P1-23 · Admin group delete has no busy guard.**
  - **Problem:** A double-click on delete can fire multiple requests; the handler also modifies props directly.
  - **Source:** `resources/js/Pages/Admin/Groups/Index.vue:85-95`.
  - **Fix:** Add `deletingId` ref and disable the trigger while a delete is in flight.

- [ ] **FE-P1-24 · Vault row action buttons overload a single click target.**
  - **Problem:** Reveal, copy, and delete buttons are visually cramped inside one row with no clear hit areas. Users frequently mis-tap and accidentally delete a secret.
  - **Source:** `resources/js/Pages/Vault/Index.vue:450-490`.
  - **Fix:** Add button labels (or tooltips) and visually separate destructive actions with a dropdown or confirmation step.

- [ ] **FE-P1-25 · Revealed vault secret is hard-truncated after 30 chars.**
  - **Problem:** Long secrets display as `****…` with no way to view the full value without copying to the clipboard.
  - **Source:** `resources/js/Pages/Vault/Index.vue:470`.
  - **Fix:** Use a scrollable `<pre>` or expandable panel for revealed secrets; keep masked by default otherwise.

- [ ] **FE-P1-26 · Import process shows no loading state.**
  - **Problem:** Clicking “Import” queues the job but gives zero feedback until polling completes. Users click repeatedly.
  - **Source:** `resources/js/Pages/Admin/Import/Index.vue:40-60`.
  - **Fix:** Set `importing = true` during submission and show an inline spinner / progress bar while `jobPolling` reports progress.

- [ ] **FE-P1-27 · No shared `<SaveIndicator>` — every page invents its own "saving…" affordance.**
  - **Problem:** Notes uses inline text `Saving…/Saved` (`Notes/Index.vue:182-185`); Files uses `form.processing + VibeSpinner` in modal footers; Photos editor uses a local `editorSaving` ref (`Photos/Index.vue:185, 417-419`); Admin pages use `:disabled="processing" + VibeSpinner`; the photo editor Save button (FE-P0-13) is an example of what goes wrong. Five different patterns, all manually wired.
  - **Source:** `Notes/Index.vue:182-185`, `Files/Index.vue:810, 849-851`, `Photos/Index.vue:185, 417-419`, `Auth/Login.vue:34-36`, `Admin/Groups/Index.vue:66, 85`, `Admin/Backups.vue:99-104`, `Admin/Import.vue:60-63`.
  - **Fix:** Build a single primitive that every page consumes, driven by `form.processing` or an explicit state:
    ```vue
    <!-- resources/js/Components/SaveIndicator.vue -->
    <script setup lang="ts">
    type State = 'idle' | 'saving' | 'saved' | 'error';
    defineProps<{ state: State; label?: string; error?: string }>();
    const map = { idle: null, saving: { icon: 'arrow-repeat', text: 'Saving…', spin: true }, saved: { icon: 'check2-circle', text: 'Saved' }, error: { icon: 'exclamation-circle', text: 'Could not save' } } as const;
    </script>
    <template>
        <span v-if="state !== 'idle'" class="d-inline-flex align-items-center gap-1 small" :class="state === 'error' ? 'text-danger' : 'text-muted'">
            <VibeSpinner v-if="state === 'saving'" size="sm" />
            <VibeIcon v-else-if="state === 'saved'" icon="check2-circle" class="text-success" />
            <VibeIcon v-else-if="state === 'error'" icon="exclamation-circle" />
            <span>{{ error || map[state]?.text }}</span>
        </span>
    </template>
    ```
    Pages bind `:state="form.processing ? 'saving' : saveState"`.

- [ ] **FE-P1-28 · UserAvatar is reimplemented 3× with different sizes.**
  - **Problem:** `AppLayout.vue:206-219` (22px), `Directory/Index.vue:74-78 + 92-95` (44/64px), `Profile/Edit.vue:84-96` (72px) all duplicate the `img v-if avatar_url else initials()` circle pattern. A change to the design system requires three edits and they already drift.
  - **Source:** `resources/js/Layouts/AppLayout.vue:206-219`, `resources/js/Pages/Directory/Index.vue:74-78, 92-95`, `resources/js/Pages/Profile/Edit.vue:84-96`.
  - **Fix:** Build the primitive once and replace every call-site:
    ```vue
    <!-- resources/js/Components/UserAvatar.vue -->
    <script setup lang="ts">
    interface UserLike { name: string; avatar_url?: string | null }
    const props = withDefaults(defineProps<{ user: UserLike; size?: number }>(), { size: 36 });
    import { initials } from '../lib/initials';
    const initial = computed(() => initials(props.user.name) || '?');
    const fontPx = computed(() => Math.max(10, Math.round(props.size * 0.42)));
    </script>
    <template>
        <img v-if="user.avatar_url" :src="user.avatar_url" :alt="user.name"
             class="rounded-circle" :style="{ width: size + 'px', height: size + 'px', objectFit: 'cover' }">
        <span v-else class="d-inline-flex align-items-center justify-content-center rounded-circle bg-primary text-white"
              :style="{ width: size + 'px', height: size + 'px', fontSize: fontPx + 'px' }">{{ initial }}</span>
    </template>
    ```
    Replace the three sites with `<UserAvatar :user="user" :size="22" />` etc.

- [ ] **FE-P1-29 · `<EmptyState>` exists but 6 surfaces still use raw `<p>No … yet</p>`.**
  - **Problem:** `EmptyState.vue` is the documented pattern and is correctly used in Files/Index, Photos, Shared/Index. But Notes (`Notes/Index.vue` no empty state at all), Bookmarks (`Bookmarks/Index.vue:219`), Vault (`Vault/Index.vue:128`), Directory (`Directory/Index.vue:67`), NotesList (`NotesList.vue:46-48`), and Profile use raw paragraphs. The tone, icon, and CTA are inconsistent.
  - **Source:** `Bookmarks/Index.vue:219`, `Vault/Index.vue:128`, `Directory/Index.vue:67`, `NotesList.vue:46-48`, `Notes/Index.vue:212-216`, `Profile/Edit.vue` (avatar).
  - **Fix:** Replace every raw empty paragraph with `<EmptyState icon="…" title="…" hint="…">` (and add an optional `cta` slot where a creation action makes sense). Standardise tone: `<empty-state>` titles are sentence case, hints are full sentences, hints include a next step.

- [ ] **FE-P1-30 · No shared `<FilterChips>` primitive — only Files/Index shows active filters.**
  - **Problem:** Files/Index renders an `activeFilters` chip bar with a clear-all (`Files/Index.vue:122-130, 656-668`). Every other listing page (Photos, Bookmarks, Notes, Audit, Search Results) silently applies filters with no visible indicator — the user can't tell why a result list is short.
  - **Source:** `Files/Index.vue:122-130, 656-668`, absence in `Photos/Index.vue:215-242`, `Bookmarks/Index.vue:198-216`, `Notes/Index.vue`, `Admin/Audit/Index.vue:62-82`.
  - **Fix:** Extract a primitive and adopt it on every listing page:
    ```vue
    <!-- resources/js/Components/FilterChips.vue -->
    <script setup lang="ts">
    interface Chip { key: string; icon: string; label: string; clear: () => void }
    defineProps<{ chips: Chip[] }>();
    const emit = defineEmits<{ clear: [Chip] }>();
    </script>
    <template>
        <VibeAlert v-if="chips.length" variant="light" class="border d-flex flex-wrap align-items-center gap-2 py-2">
            <VibeIcon icon="funnel-fill" class="text-muted" />
            <VibeBadge v-for="c in chips" :key="c.key" variant="secondary" class="d-flex align-items-center gap-1">
                <VibeIcon :icon="c.icon" />{{ c.label }}
                <VibeIcon icon="x" style="cursor: pointer" :aria-label="`Clear ${c.label}`" @click="emit('clear', c)" />
            </VibeBadge>
        </VibeAlert>
    </template>
    ```

- [ ] **FE-P1-31 · `usePageLoading` is mounted on 8 pages but missing on 6.**
  - **Problem:** The composable exists and is wired into Photos, Storage, Admin/Users, Admin/Groups, Admin/Audit, Admin/Backups, Admin/Import, Shared/Index, Shared/Folder, Errors. It is **not** used in Files/Index, Notes/Index, Bookmarks/Index, Vault/Index, Directory/Index, Profile/Edit — so a navigation from Files → Bookmarks flashes stale content with no skeleton.
  - **Source:** `resources/js/composables/usePageLoading.ts`, presence in `Photos/Index.vue:14-15` and Admin pages, absence in `Files/Index.vue`, `Notes/Index.vue`, `Bookmarks/Index.vue`, `Vault/Index.vue`, `Directory/Index.vue`, `Profile/Edit.vue`.
  - **Fix:** Add `const { loading } = usePageLoading();` to the six missing pages and gate their primary content block on `v-if="!loading"` (or pair with `<LoadingSkeleton>`).

- [ ] **FE-P1-32 · No command palette — ⌘K is hard-wired to the file-search box only.**
  - **Problem:** `AppLayout.vue:60-67` traps ⌘K to focus `global-search`. Power users expect ⌘K to open an action palette (New Folder, Empty Trash, Switch Theme, Open Settings, Move Selection to…) plus a typed-as-you-go file search. Currently the shortcut is half-built.
  - **Source:** `resources/js/Layouts/AppLayout.vue:60-67`, no command palette in `lib/` or `composables/`.
  - **Fix:** Build a real command palette:
    ```vue
    <!-- resources/js/Components/CommandPalette.vue -->
    <script setup lang="ts">
    import { ref, computed } from 'vue';
    import { router } from '@inertiajs/vue3';
    const open = defineModel<boolean>({ required: true });
    const q = ref('');
    const items = computed(() => [
        { group: 'Actions', entries: [
            { text: 'New folder', icon: 'folder-plus', onPick: () => router.visit('/?new=folder') },
            { text: 'Empty trash', icon: 'trash', onPick: () => router.delete('/trash/empty', { preserveScroll: true }) },
            { text: 'Switch theme', icon: 'circle-half', onPick: () => useColorMode().toggleColorMode() },
        ] },
        // results from /api/search merged in
    ]);
    </script>
    ```
    Replace the AppLayout ⌘K handler with `useCommandPalette().toggle()` and mount `<CommandPalette v-model="open" />` once at the AppLayout level.

- [ ] **FE-P1-33 · Files/Index `Space` handler closes Quick Look while typing in the page's local search input.**
  - **Problem:** `Files/Index.vue:399-422` shortcuts: `Space` closes QL when QL is open, and opens QL on the active row when QL is closed. The handler at line 408 checks `e.target.tagName` and exits for `input/textarea/select` — but `Files/Index` has its own `search` ref bound to its own input (line 84), **not** `global-search` in `AppLayout`. Typing a space in the Files search bar with QL open silently closes QL.
  - **Source:** `resources/js/Pages/Files/Index.vue:84, 399-422`.
  - **Fix:** Distinguish "typing in any input" from "QL open" with an explicit close-suppression:
    ```ts
    function onKey(e) {
        const tag = (e.target?.tagName || '').toLowerCase();
        const inField = ['input', 'textarea', 'select'].includes(tag) || e.target?.isContentEditable;
        if (inField) return; // never steal keys from a focused field
        if (quickOpen.value && e.key === ' ') { e.preventDefault(); quickClose(); return; }
        if (e.key === ' ' && props.files.length) { e.preventDefault(); quickOpenSelected(); }
    }
    ```
    Same pattern for Photos/Index.vue:108-115 (currently `Escape` is fine but `Space` should also be allowed when no input is focused).

- [ ] **FE-P1-34 · Photos lightbox header packs 5 secondary buttons — no clear primary action.**
  - **Problem:** `Photos/Index.vue:323-345` renders Slideshow / Star / Edit / Download / Delete side-by-side, all `outline secondary` except Download. Apple would pick Download as the single `primary` and group the rest in a `…` menu. As-is, the Delete button is one missed tap away from the Slideshow button.
  - **Source:** `resources/js/Pages/Photos/Index.vue:323-345`.
  - **Fix:** One primary, the rest in a menu:
    ```vue
    <template #header>
        <div class="d-flex align-items-center w-100">
            <span class="text-truncate flex-grow-1 me-3">{{ currentPhoto?.name }}</span>
            <VibeButton v-if="currentPhoto" variant="primary" size="sm" :href="`/download/${currentPhoto.id}`"><VibeIcon icon="download" class="me-1" />Download</VibeButton>
            <VibeDropdown v-if="currentPhoto" variant="secondary" size="sm" menu-end :items="photoMenu(currentPhoto)" @item-click="onPhotoMenu(currentPhoto, $event)">
                <VibeIcon icon="three-dots-vertical" />
            </VibeDropdown>
            <VibeButton variant="secondary" size="sm" outline @click="lightboxOpen = false"><VibeIcon icon="x-lg" /></VibeButton>
        </div>
    </template>
    ```

- [ ] **FE-P1-35 · Vault row actions are cramped — easy to mis-click Remove.**
  - **Problem:** `Vault/Index.vue:146-157` renders Reveal / Copy / Edit / Remove as four small `outline`/`light` buttons in a single row, with no labels. The eye scans left-to-right and the destructive button is rightmost, exactly where iOS/Android would put a swipe-confirm.
  - **Source:** `resources/js/Pages/Vault/Index.vue:146-157`.
  - **Fix:** Add visible labels, group edit+remove in a dropdown, and use a "long press / second tap" pattern for destructive:
    ```vue
    <div class="d-flex align-items-center gap-1" @click.stop>
        <VibeButton size="sm" variant="primary" :title="revealed[item.id] ? 'Hide' : 'Reveal'" @click="reveal(item)">
            <VibeIcon :icon="revealed[item.id] ? 'eye-slash' : 'eye'" class="me-1" />{{ revealed[item.id] ? 'Hide' : 'Reveal' }}
        </VibeButton>
        <VibeButton v-if="revealed[item.id]" size="sm" variant="secondary" outline @click="copy(item.id)">
            <VibeIcon icon="clipboard" class="me-1" />Copy
        </VibeButton>
        <VibeDropdown v-if="item.can_edit" size="sm" variant="light" menu-end :items="[{ text: 'Edit', icon: 'pencil', action: 'edit' }, { text: 'Remove', icon: 'trash', action: 'delete' }]" @item-click="onItemMenu(item, $event)">
            <VibeIcon icon="three-dots-vertical" />
        </VibeDropdown>
    </div>
    ```

- [ ] **FE-P1-36 · Files/Index does not use `usePageLoading` — no skeleton on full navigations.**
  - **Problem:** All other listing pages (`Photos/Index`, `Storage/Index`, `Admin/*`, `Trash/Index`, `Shared/Index`, `Shared/Folder`, `Errors/Error`) wire `usePageLoading` and show `<LoadingSkeleton>` while the next Inertia response is in flight. Files/Index — the most-visited page — flashes the previous folder's content for ~150–400 ms.
  - **Source:** `resources/js/Pages/Files/Index.vue` (absent), present in `Photos/Index.vue:15`, `Storage/Index.vue:12`, `Trash/Index.vue:10`, `Shared/Index.vue:14`.
  - **Fix:** Add `const { loading } = usePageLoading();` and gate the data table / grid on `v-if="!loading"`, with `<LoadingSkeleton :rows="6" :cols="4" />` as the placeholder.

- [ ] **FE-P1-37 · Photo `star` is not optimistic — visible UI lag on click.**
  - **Problem:** `Photos/Index.vue:130-133` calls `router.post('/files/{id}/star')` with no `onSuccess` to update local state. After clicking, the icon stays unstarred for 200–600 ms (network + Inertia round-trip), then Inertia's partial reload re-paints.
  - **Source:** `resources/js/Pages/Photos/Index.vue:130-133`, `star()` invoked from cell button at line 304 and from lightbox at line 331.
  - **Fix:** Toggle locally first, roll back on error:
    ```ts
    function star(p) {
        if (!p) return;
        const next = !p.starred;
        p.starred = next; // optimistic
        router.post(`/files/${p.id}/star`, {}, {
            preserveScroll: true,
            preserveState: false,
            onError: () => { p.starred = !next; toast.push('Could not update star', { variant: 'danger' }); },
        });
    }
    ```
    Same pattern applies to `Files/Index.vue:156-158` `toggleStar` (less visible because the Inertia full reload swaps the row).

- [ ] **FE-P1-38 · AppLayout logo uses a raw anchor + `@click` instead of an Inertia `<Link>`.**
  - **Problem:** `AppLayout.vue:155-163` renders `<a class="d-flex align-items-center text-decoration-none ..." @click="router.visit('/')" :title="…">`. Cmd-click / middle-click open the same page in a new tab; the right-click context menu has no "Open in new tab" either.
  - **Source:** `resources/js/Layouts/AppLayout.vue:155-163`.
  - **Fix:** Use Inertia's `<Link>` and drop the click handler:
    ```vue
    <Link href="/" class="d-flex align-items-center text-decoration-none flex-shrink-0" style="cursor: pointer; min-width: 218px" :title="`${appName} — ${tagline}`">
        <VibeIcon icon="rocket-takeoff-fill" class="text-primary fs-4 me-2" />
        <span class="d-none d-md-flex flex-column lh-1">
            <span class="fw-bold fs-5 text-body">{{ appName }}</span>
            <span class="text-muted" style="font-size: 0.62rem; letter-spacing: 0.02em">{{ tagline }}</span>
        </span>
    </Link>
    ```

- [ ] **FE-P1-39 · Five editors, three chrome patterns — Markdown/HTML/CSV (modal), DOCX/XLSX (full-page route), Photos (in-modal cropper).**
  - **Problem:** `EditorModal.vue` (modal centered), `Files/Editor.vue` (full-page route at `/files/{id}/edit` with `SpreadsheetEditor`/`DocxEditor`), and `Photos/Index.vue:409-429` (in-modal `Cropper`) all edit files but with different chrome, different top bars, different save flows. A power user with a doc and a sheet open in two tabs sees two different editors.
  - **Source:** `resources/js/Components/EditorModal.vue`, `resources/js/Pages/Files/Editor.vue`, `resources/js/Components/SpreadsheetEditor.vue`, `resources/js/Components/DocxEditor.vue`, `resources/js/Pages/Photos/Index.vue:409-429`.
  - **Fix:** Pick two patterns and stick to them: **(a) inline modal** for text/markdown/html/photos and **(b) full-page route** for binary office files. Either fold `Files/Editor.vue` into a modal on Files/Index (using `<EditorModal>` with `kind: 'sheet' | 'docx'`), or move Photos cropper to `/photos/{id}/edit`. Document the choice in `docs/editor-chrome.md`.

- [ ] **FE-P1-40 · Vault `copy()` is silent — no "Copied!" feedback.**
  - **Problem:** `Vault/Index.vue:56-58` writes to the clipboard with `navigator.clipboard?.writeText(revealed[id])` and shows nothing. The user can't tell if the copy succeeded, especially in older browsers where `navigator.clipboard` is `undefined`.
  - **Source:** `resources/js/Pages/Vault/Index.vue:56-58`.
  - **Fix:** Use the new `useToast` from FE-P0-26, or at minimum mirror `ShareModal.vue:467-471`'s 1.5 s check-icon pattern:
    ```ts
    const copied = ref<number | null>(null);
    let copyTimer: ReturnType<typeof setTimeout> | null = null;
    async function copy(id: number) {
        if (!revealed[id]) return;
        try {
            await navigator.clipboard.writeText(revealed[id]);
            copied.value = id;
            if (copyTimer) clearTimeout(copyTimer);
            copyTimer = setTimeout(() => { copied.value = null; }, 1500);
        } catch { toast.push('Could not copy to clipboard', { variant: 'danger' }); }
    }
    onBeforeUnmount(() => { if (copyTimer) clearTimeout(copyTimer); });
    ```
    And render `<VibeIcon :icon="copied === item.id ? 'check' : 'clipboard'" />` so the user gets visual confirmation.

- [ ] **FE-P1-46 · "Add" vs "Create" verb is split across create flows.**
  - **Problem:** Vault uses **Add** secret (`Vault/Index.vue:123, 184`); Bookmarks uses **Add** bookmark (`Bookmarks/Index.vue:212, 293, 322`); Photos says **New Album** (`Photos/Index.vue:236, 398`); Notes folder **Create** (`Notes/Index.vue:62`); Files editor **Create document** (`Files/Editor.vue:136`); Admin/Groups **Add group** (`Admin/Groups/Index.vue:66`); Auth **Create account** (`Auth/Register.vue:13, 51`). Six forms say "Add" for objects, three say "Create" — confused vocabulary, not Apple-style.
  - **Source:** See file:line refs above.
  - **Fix:** Pick one rule (Apple's: **Create** for objects that exist after the action, **Add** only when adding to an existing collection). Document in `docs/copy.md` and grep-replace:
    - Auth: keep **Create** account / **Sign in** (auth vocabulary is established).
    - All app objects (Vault, Bookmarks, Notes, Photos, Files, Groups, Albums, Folders): **Create** `{name}`. Button label: `+ Create` or just `Create`. Modal title: `Create {name}`.
    - "Add to" only for **Add to album**, **Add to group**, **Add tag** (adding to an existing container).
    - `confirmLabel: 'Add'` → `confirmLabel: 'Create'`, button text "Add secret" → "Create secret", etc.

- [ ] **FE-P1-47 · Icon-only buttons missing `aria-label` — screen-reader users hear "button" with no purpose.**
  - **Problem:** ~30 icon-only `<VibeButton>` instances have no `aria-label` (only `title` attributes, which are not announced reliably by screen readers and disappear on touch). Examples: `Files/Index.vue:606-623, 626-641, 791-792, 806`; `Bookmarks/Index.vue:158, 213, 220, 225, 251-256`; `Vault/Index.vue:120-124, 146-157`; `Files/Editor.vue:91-106`; `Admin/Groups/Index.vue:93-97`. WCAG 4.1.2 (Name, Role, Value) violated.
  - **Source:** See file:line refs above.
  - **Fix:** Establish a lint rule (eslint-plugin-vue with `vuejs-accessibility/anchor-has-content` extended) and apply systematically:
    ```vue
    <!-- example: every VibeButton that wraps only an icon must have aria-label -->
    <VibeButton variant="secondary" size="sm" outline title="Tags" aria-label="Open tags for {item.name}" @click="openTags(item)">
        <VibeIcon icon="tags" />
    </VibeButton>
    ```
    Add a CI test that greps `<VibeButton[^>]*\n[^>]*<VibeIcon[^>]*/>` without `aria-label` and fails the build.

- [ ] **FE-P1-48 · Bookmarks form has a dead `color` field — set in code, never rendered.**
  - **Problem:** `Bookmarks/Index.vue:80` `useForm({ title: '', url: '', description: '', icon: '', color: '', category: '', shared: false })`. The Add Bookmark modal (line 293-325) renders Title, URL, Description, Folder, Icon, Shared — but **no `color` field**. The form value is still sent (`form.color = ''`) and the backend may persist it. Confusing dead UI state.
  - **Source:** `resources/js/Pages/Bookmarks/Index.vue:80, 293-325`.
  - **Fix:** Either remove `color` from the form object (if unused) or expose a color picker in the modal:
    ```vue
    <VibeFormGroup label="Color (optional)" class="mt-2">
        <VibeFormInput v-model="form.color" type="color" class="form-control-color" />
    </VibeFormGroup>
    ```
    Recommended: remove from form until the UI supports it; add a `BE` ticket for the colour feature.

- [ ] **FE-P1-49 · Bookmarks folder field uses a static `<datalist>` instead of an autocomplete with the live folder list.**
  - **Problem:** `Bookmarks/Index.vue:305-310` wraps the folder input in `<datalist id="bm-folders">` with a hardcoded `<option>` per folder. For 50+ folders, the user has to scroll the entire list (no filter). `<datalist>` is also not keyboard-navigable the way an autocomplete is, and it doesn't surface the count.
  - **Source:** `resources/js/Pages/Bookmarks/Index.vue:305-310` and the hardcoded `<datalist id="bm-folders">` rendering.
  - **Fix:** Replace with `<VibeAutocomplete>`:
    ```vue
    <VibeAutocomplete
        v-model="form.category"
        :source="folderNames"
        label="Folder"
        placeholder="Type a folder name…"
        help-text="Pick an existing folder or type a new one to create it."
        @select="(item) => form.category = item.text"
    />
    ```
    Where `folderNames = computed(() => props.folders ?? [])`. The autocomplete already exists and is used in the Files Tags modal.

- [ ] **FE-P1-50 · No "show password" toggle on any password field.**
  - **Problem:** `Auth/Login.vue:29`, `Auth/Register.vue:38, 46`, `Auth/ResetPassword.vue:39, 47`, `Auth/ConfirmPassword.vue:21`, `Profile/Edit.vue:131, 139, 150`, `Admin/Users/Edit.vue:71, 78`, `Vault/Index.vue` (reveal-confirm `prompt()`), `Public/Share.vue:37` all render `<input type="password">` with no toggle. Apple, Google, Microsoft, 1Password — every modern form has this. Mobile users in particular mistype 16+ char passwords constantly.
  - **Source:** All password inputs in the codebase.
  - **Fix:** Add a `show-password` prop to `VibeFormInput` (or wrap):
    ```vue
    <script setup>
    const props = defineProps<{ modelValue: string; type: string; showToggle?: boolean; /* … */ }>();
    const emit = defineEmits(['update:modelValue']);
    const showPw = ref(false);
    const effectiveType = computed(() => (props.type === 'password' && props.showToggle && showPw.value) ? 'text' : props.type);
    </script>
    <template>
        <div class="input-group">
            <input :type="effectiveType" :value="modelValue" @input="emit('update:modelValue', ($event.target as HTMLInputElement).value)" class="form-control" v-bind="$attrs" />
            <button v-if="showToggle && type === 'password'" type="button" class="btn btn-outline-secondary" :aria-label="showPw ? 'Hide password' : 'Show password'" :aria-pressed="showPw" @click="showPw = !showPw">
                <VibeIcon :icon="showPw ? 'eye-slash' : 'eye'" />
            </button>
        </div>
    </template>
    ```
    Adopt on every password field with `:show-toggle="true"`.

- [ ] **FE-P1-51 · `Admin/Users/Edit.vue` Cancel button uses `href="/users"` — hard navigation, kills SPA.**
  - **Problem:** `Admin/Users/Edit.vue:83` `<VibeButton variant="secondary" outline href="/users">Cancel</VibeButton>`. The browser does a full page reload, losing scroll position, in-flight requests, and any in-progress other-tab state. Every other Cancel in the app uses a `@click` or a router method; this one is the lone outlier.
  - **Source:** `resources/js/Pages/Admin/Users/Edit.vue:83`.
  - **Fix:** Use Inertia's `<Link>` (consistent with `Auth/ForgotPassword.vue:32`, `Auth/Register.vue:55`):
    ```vue
    <Link href="/users" class="btn btn-secondary btn-outline">Cancel</Link>
    ```
    Or keep `VibeButton` but route via the Inertia router:
    ```vue
    <VibeButton variant="secondary" outline @click="router.visit('/users')">Cancel</VibeButton>
    ```

- [ ] **FE-P1-52 · No password strength meter on Register or Reset Password.**
  - **Problem:** `Auth/Register.vue:32-39` and `Auth/ResetPassword.vue:33-40` render a plain password input with no strength feedback. Users pick weak passwords, get a server 422 ("must be 8+ chars, mixed case, number, symbol"), and don't know how to fix it. The form is a guest form — first-impression UX matters.
  - **Source:** `resources/js/Pages/Auth/Register.vue:32-39`, `resources/js/Pages/Auth/ResetPassword.vue:33-40`.
  - **Fix:** Add a strength meter under the password input:
    ```vue
    <VibeFormGroup label="Password" :error="form.errors.password">
        <VibeFormInput v-model="form.password" type="password" required autocomplete="new-password" show-strength />
    </VibeFormGroup>
    ```
    Compute strength client-side:
    ```ts
    // resources/js/lib/passwordStrength.ts
    export function strength(pw: string): { score: 0 | 1 | 2 | 3 | 4; label: string } {
        let s = 0;
        if (pw.length >= 8) s++;
        if (pw.length >= 12) s++;
        if (/[A-Z]/.test(pw) && /[a-z]/.test(pw)) s++;
        if (/\d/.test(pw)) s++;
        if (/[^A-Za-z0-9]/.test(pw)) s++;
        return { score: Math.min(4, s) as 0 | 1 | 2 | 3 | 4, label: ['Too weak', 'Weak', 'Fair', 'Good', 'Strong'][Math.min(4, s)] };
    }
    ```
    Render a 4-segment bar with the label. Apply the same to `ResetPassword.vue`.

- [ ] **FE-P1-53 · No "unsaved changes" guard on edit modals — closing with typed data silently discards it.**
  - **Problem:** Every edit modal (Bookmarks add/edit, Vault add/edit, Files rename/transfer/tags, Photos album, Admin/Users/Edit, Notes rename) can be closed by Esc, X, backdrop click, or Cancel without warning. Typed data is lost. No `beforeRouteLeave` or `beforeunload` for full-page nav either. WCAG 3.3.4 (Error Prevention) recommends a confirmation.
  - **Source:** All edit modals (see F8 in the forms catalog).
  - **Fix:** Add a shared composable:
    ```ts
    // resources/js/composables/useDirtyGuard.ts
    import { onBeforeUnmount, onBeforeRouteLeave } from 'vue-router';
    export function useDirtyGuard(isDirty: Ref<boolean>, message = 'You have unsaved changes. Discard them?') {
        function confirmDiscard(): boolean { return !isDirty.value || window.confirm(message); }
        // For Inertia navigations
        document.addEventListener('inertia:before', (e) => {
            if (isDirty.value && !window.confirm(message)) e.preventDefault();
        });
        onBeforeUnmount(() => document.removeEventListener('inertia:before', /* same */));
        return { confirmDiscard };
    }
    ```
    Use in each page:
    ```vue
    <script setup>
    const isDirty = computed(() => form.isDirty); // Inertia's useForm exposes this
    useDirtyGuard(isDirty);
    </script>
    ```
    For modal backdrop close, VibeModal's `@hide` event should call `confirmDiscard()` before closing.

- [ ] **FE-P1-54 · Form labels are missing or not associated with controls.**
  - **Problem:** Several inputs rely on placeholder text or detached visual labels, so screen readers cannot reliably identify the control's purpose.
  - **Source:** `resources/js/Components/AdvancedSearchModal.vue`, `resources/js/Components/ShareModal.vue`, `resources/js/Pages/Directory/Index.vue`.
  - **Fix:** Use `VibeFormGroup` with explicit `for`/`id` linking, or wrap the input inside a real `<label>`. Remove placeholder-only labeling.

- [ ] **FE-P1-55 · App header and three-pane layouts lack landmarks and a skip link.**
  - **Problem:** The app shell uses generic `<div>` wrappers instead of `<main>`, `<aside>`, and `<nav>` landmarks, and there is no skip link to bypass repeated navigation.
  - **Source:** `resources/js/Layouts/AppLayout.vue`, `resources/js/Components/ThreePane.vue`.
  - **Fix:** Add a "Skip to main content" focusable link at the top of `AppLayout` and render semantic landmark elements around the sidebar, page nav, and main content regions.

- [ ] **FE-P1-56 · Icon-only buttons and small touch targets are below 44×44 px.**
  - **Problem:** Many icon actions are either non-focusable glyphs or wrapped in buttons smaller than the WCAG 2.5.8 minimum, making them hard to hit on touch screens and unusable with a keyboard.
  - **Source:** `resources/js/Pages/Files/Index.vue`, `resources/js/Layouts/AppLayout.vue`, `resources/js/Components/FileItem.vue`, `resources/js/Pages/Photos/Index.vue`.
  - **Fix:** Ensure every icon action is a real `<button>` with a min 44×44 px hit area and an accessible name via `aria-label` or visible text.

- [ ] **FE-P1-57 · ShareModal grant/link actions fail silently.**
  - **Problem:** Adding, removing, and revoking share grants/links do not surface network or validation errors to the user, so failed mutations appear to succeed.
  - **Source:** `resources/js/Components/ShareModal.vue`.
  - **Fix:** Add `busy` and `error` states to share mutations, disable controls while a mutation is in flight, and render inline error feedback.

- [ ] **FE-P1-58 · Directory profile fetch swallows errors.**
  - **Problem:** When the profile request fails, the modal opens but shows a blank card with no error message.
  - **Source:** `resources/js/Pages/Directory/Index.vue`.
  - **Fix:** Catch request failures and render an inline error alert inside the profile modal.

- [ ] **FE-P1-59 · Notes autosave hides failures.**
  - **Problem:** If the autosave request fails, the save badge resets to idle and the user assumes the note is saved, risking data loss.
  - **Source:** `resources/js/Pages/Notes/Index.vue`.
  - **Fix:** Expose an error state in the save indicator and keep showing a failure message until the next successful save or user action.

- [ ] **FE-P1-60 · UploadModal has no per-file progress, cancellation, or retry.**
  - **Problem:** The upload dialog only shows overall progress. Failed or large uploads cannot be cancelled, and users cannot retry individual files.
  - **Source:** `resources/js/Components/UploadModal.vue`.
  - **Fix:** Use an `AbortController` for the upload request and render per-file progress rows with cancel and retry actions.

- [ ] **FE-P1-61 · BatchActions busy guard only disables Delete.**
  - **Problem:** While a batch request is running, the Move, Rename, and New Folder buttons remain enabled, allowing overlapping mutations.
  - **Source:** `resources/js/Components/BatchActions.vue`.
  - **Fix:** Disable every button on the batch action bar and the primary action inside the batch modals while `batchBusy` is true.

### FE-P2 — Correctness / Performance

- [ ] **FE-P2-20 · `useSelection` shift-selection handles indexes before selection start.**
  - **Problem:** `shiftLastIndex` is reset on every click, so Shift+click on an item below the anchor only works in one direction; range math also indexes into `-1`.
  - **Source:** `resources/js/composables/useSelection.ts:30-50`.
  - **Fix:** Track `anchorIndex` and compute `Math.min/max` with the current click index:
    ```ts
    function selectRange(toIndex: number) {
        const start = Math.min(anchorIndex.value, toIndex);
        const end = Math.max(anchorIndex.value, toIndex);
        for (let i = start; i <= end; i++) selected.add(i);
    }
    ```

- [ ] **FE-P2-21 · `useQuickLook` fallback picks first file instead of selected file.**
  - **Problem:** `quickLook(initialIndex)` ignores its argument and always falls back to `files.value[0]`.
  - **Source:** `resources/js/composables/useQuickLook.ts:22`.
  - **Fix:**
    ```ts
    function quickLook(initialIndex = 0) {
        const idx = Math.max(0, Math.min(initialIndex, files.value.length - 1));
        currentIndex.value = idx;
        isOpen.value = true;
    }
    ```

- [ ] **FE-P2-22 · `useBusyGuard` does not await async operations.**
  - **Problem:** The guard toggles the busy flag synchronously; concurrent async handlers can overlap and the guard appears to do nothing for real async work.
  - **Source:** `resources/js/composables/useBusyGuard.ts`.
  - **Fix:** Accept an async callback and use a queue or semaphore:
    ```ts
    export function useBusyGuard() {
        const isBusy = ref(false);
        async function guard<T>(fn: () => Promise<T>): Promise<T> {
            if (isBusy.value) throw new Error('busy');
            isBusy.value = true;
            try { return await fn(); } finally { isBusy.value = false; }
        }
        return { isBusy, guard };
    }
    ```

- [ ] **FE-P2-23 · Batch rename re-renders O(n²) per keystroke.**
  - **Problem:** Template string mapping and index slicing are done inline in the template, so every character typed re-evaluates the whole list.
  - **Source:** `resources/js/components/BatchRenameModal.vue:75-90`.
  - **Fix:** Compute `previewList` with `computed()` and bind rows to it.

- [ ] **FE-P2-24 · Raw prop references in modals mutate parent objects.**
  - **Problem:** `const file = props.file; file.name = newName` edits the prop object directly; this bypasses Vue warnings and corrupts shared state.
  - **Source:** Multiple modals (`RenameModal`, `TagsModal`, etc.).
  - **Fix:** Always clone mutable working state from props:
    ```vue
    <script setup>
    const form = useForm({ name: props.file.name });
    </script>
    ```

- [ ] **FE-P2-25 · Heavy editors are imported eagerly in app chunk.**
  - **Problem:** `CKEditor`, `Monaco`, and spreadsheet components add to initial bundle even though they are used only in edit modals.
  - **Source:** `resources/js/components/EditorModal.vue`, `SpreadsheetEditor.vue`.
  - **Fix:** Use `defineAsyncComponent` for each editor pane.

- [ ] **FE-P2-26 · MarkdownEditor is imported eagerly on Files/Index.**
  - **Problem:** `MarkdownEditor` is bundled with the file listing page even though it is only shown inside a modal.
  - **Source:** `resources/js/Pages/Files/Index.vue` import graph.
  - **Fix:** Move the editor into `EditorModal` or lazy-load it.

- [ ] **FE-P2-27 · Vault edit flow drops `notes` field.**
  - **Problem:** The edit form only submits `name` and `value`; any existing notes are overwritten to empty on save.
  - **Source:** `resources/js/Pages/Vault/Index.vue:120-135`.
  - **Fix:** Include `notes` in the form payload, or switch to partial update.

- [ ] **FE-P2-28 · Preview iframe `src` / link `href` not allowlisted.**
  - **Problem:** Files with arbitrary URLs can be rendered in an iframe or opened via generated link, creating phishing / XSS surface.
  - **Source:** `resources/js/components/QuickLook.vue`, `FilePreview.vue`.
  - **Fix:** Sanitise URL scheme/protocol before iframe `src` and external link `href`; reject `javascript:`, `data:` and unknown protocols.

- [ ] **FE-P2-29 · Runtime-only `defineProps` loses type safety.**
  - **Problem:** `defineProps({ ... })` instead of `defineProps<Props>()` means no compile-time prop checking and props are typed as `any`.
  - **Source:** Common in older components.
  - **Fix:** Migrate to TypeScript-style `defineProps<...>()` with interfaces; remove runtime validators only where they duplicate types.

- [ ] **FE-P2-30 · Spreadsheet numeric formula uses `isNaN()` without Number coercion.**
  - **Problem:** `isNaN(v)` returns false for strings such as `'123'`, leading to incorrect numeric treatment.
  - **Source:** `resources/js/components/SpreadsheetEditor.vue:85`.
  - **Fix:** Use `Number.isNaN(Number(v))` or `typeof v === 'number' && !Number.isNaN(v)`.

- [ ] **FE-P2-31 · Editor fullscreen toggle swallows errors.**
  - **Problem:** Requesting fullscreen can reject; the handler only calls `requestFullscreen()` without `catch`, throwing an unhandled promise rejection.
  - **Source:** `resources/js/components/EditorModal.vue:150`.
  - **Fix:**
    ```ts
    async function enterFullscreen() {
        try { await editorContainer.value?.requestFullscreen(); } catch { /* ignore */ }
    }
    ```

- [ ] **FE-P2-32 · Photos slideshow resumes playing when modal reopens.**
  - **Problem:** `slideshowTimer` is created in `onMounted` and may not be reset when the modal closes or unmounts.
  - **Source:** `resources/js/Pages/Photos/Index.vue:220-240`.
  - **Fix:** Pause/clear the timer on `onBeforeUnmount` and whenever `lightboxOpen` becomes false.

- [ ] **FE-P2-33 · Directory profile fetch errors are silent.**
  - **Problem:** `showProfile` does not catch network failures; broken profiles leave the card blank.
  - **Source:** `resources/js/Pages/Directory/Index.vue:40-60`.
  - **Fix:** Add `try/catch` and render an error state in the profile panel.

- [ ] **FE-P2-34 · Notes autosave gate is fragile.**
  - **Problem:** `suppressSave` is a plain variable, not ref/reactive, and the comparison `content.value !== original` can save while a save is in flight.
  - **Source:** `resources/js/Pages/Notes/Index.vue:80-108`.
  - **Fix:** Use `isLoading`/`isSaving` flags and debounce-save only when idle.

- [ ] **FE-P2-35 · Vault treats empty secret value as falsy and skips masking.**
  - **Problem:** Revealed-value logic uses `!value` to decide whether to mask, so an empty secret never masks.
  - **Source:** `resources/js/Pages/Vault/Index.vue:165`.
  - **Fix:** Track revealed state explicitly with a `Set<number>` instead of relying on value truthiness.

- [ ] **FE-P2-36 · `fileMenu(item)` recomputes the action list on every render.**
  - **Problem:** `Files/Index.vue:184` defines `fileMenu(item)` as a function, and it's called twice in the template (line 679 and 754). Every keystroke / hover / tick re-filters `fileActions` from scratch. With a 1000-item grid that's 2000 function calls per frame.
  - **Source:** `resources/js/Pages/Files/Index.vue:184, 679, 754`.
  - **Fix:** Compute once per item, cache in a `computed` map:
    ```vue
    <script setup>
    const menuByItem = computed(() => {
        const map = new Map<number, ActionItem[]>();
        for (const i of items.value) {
            map.set(i.id, i.is_dir ? folderActions : fileMenu(i));
        }
        return map;
    });
    </script>
    <template>
        <FileItem :menu="menuByItem.get(item.id) ?? []" … />
    </template>
    ```

- [ ] **FE-P2-37 · `Notes/Index.vue` outline indents headings with `' '.repeat()` — fragile in non-monospace fonts.**
  - **Problem:** `Notes/Index.vue:70-75` builds outline text as `' '.repeat((h.level - 1) * 2) + h.text` and renders it with `white-space: pre` (line 198-199). On any non-monospace font, the spaces don't match the glyph width and the indent looks ragged. Sub-headings also overflow the dropdown.
  - **Source:** `resources/js/Pages/Notes/Index.vue:70-75, 197-199`.
  - **Fix:** Drop the space trick and indent with CSS:
    ```ts
    const outline = computed(() =>
        extractHeadings(content.value).map((h) => ({ ...h, level: h.level, text: h.text })),
    );
    ```
    ```vue
    <template #item="{ item }">
        <span class="font-monospace text-muted me-1">H{{ item.level }}</span>
        <span class="text-truncate" :style="{ paddingLeft: (item.level - 1) * 0.5 + 'rem' }">{{ item.text }}</span>
    </template>
    ```

- [ ] **FE-P2-38 · `Storage/Index.vue` inlines a `ResizeObserver` + `setTimeout` debounce instead of a composable.**
  - **Problem:** `Storage/Index.vue:39-58` mounts a `ResizeObserver` on a box element, debounces size changes with a hand-rolled `setTimeout`, and tears down both in `onBeforeUnmount`. The same pattern will be needed in `Files/Index` (for the grid view), `Photos/Index` (filmstrip), and any future detail-pane. Three copies of the same code is the threshold for extraction.
  - **Source:** `resources/js/Pages/Storage/Index.vue:39-58`.
  - **Fix:** Extract once:
    ```ts
    // resources/js/composables/useElementSize.ts
    import { ref, onMounted, onBeforeUnmount, type Ref } from 'vue';
    export function useElementSize(el: Ref<HTMLElement | null>, debounceMs = 120) {
        const size = ref({ width: 0, height: 0 });
        let ro: ResizeObserver | null = null;
        let t: ReturnType<typeof setTimeout> | null = null;
        function apply() {
            if (!el.value) return;
            size.value = { width: el.value.clientWidth, height: el.value.clientHeight };
        }
        onMounted(() => {
            apply();
            if (typeof ResizeObserver !== 'undefined' && el.value) {
                ro = new ResizeObserver(() => {
                    if (t) clearTimeout(t);
                    t = setTimeout(apply, debounceMs);
                });
                ro.observe(el.value);
            }
        });
        onBeforeUnmount(() => { ro?.disconnect(); if (t) clearTimeout(t); });
        return size;
    }
    ```
    Then `Storage/Index.vue` becomes:
    ```ts
    const box = ref<HTMLElement | null>(null);
    const dims = useElementSize(box);
    ```

- [ ] **FE-P2-39 · `Admin/Backups.vue` inlines a `setInterval` poll instead of using `useJobPolling`.**
  - **Problem:** `Admin/Backups.vue:64-71` starts `setInterval(refresh, 4000)` in `onMounted` and clears it in `onBeforeUnmount`. The same shape exists as `useJobPolling` (`useJobPolling.ts`) and is already used by `Files/Index.vue:557-561`. Two implementations of "poll while a job is pending" is a code smell.
  - **Source:** `resources/js/Pages/Admin/Backups.vue:64-71`, `resources/js/composables/useJobPolling.ts`.
  - **Fix:** Replace with the composable:
    ```ts
    import { useJobPolling } from '../../composables/useJobPolling';
    const items = computed(() => props.backups);
    const { start: startPolling } = useJobPolling(items, () =>
        router.reload({ only: ['backups'], preserveScroll: true }),
    );
    onMounted(startPolling);
    ```

- [ ] **FE-P2-40 · `Admin/Import.vue` inlines a `setInterval` for a 15s count refresh.**
  - **Problem:** `Admin/Import.vue:19-21` does `setInterval(refreshCount, 15000)` and clears on unmount. Not a job-polling case (there's no `status: 'pending'`) but the pattern is the same.
  - **Source:** `resources/js/Pages/Admin/Import.vue:19-21`.
  - **Fix:** Extract a tiny `useInterval(fn, ms)` composable:
    ```ts
    // resources/js/composables/useInterval.ts
    import { onMounted, onBeforeUnmount } from 'vue';
    export function useInterval(fn: () => void, ms: number) {
        let id: ReturnType<typeof setInterval> | null = null;
        onMounted(() => { id = setInterval(fn, ms); });
        onBeforeUnmount(() => { if (id) clearInterval(id); });
    }
    ```
    `Admin/Import.vue`: `useInterval(refreshCount, 15000);`.

- [ ] **FE-P2-41 · `Admin/Groups/Index.vue` deletes with `useForm({}).delete(...)` — a throwaway form for a one-liner.**
  - **Problem:** `Admin/Groups/Index.vue:46` does `useForm({}).delete(\`/groups/${group.id}\`, …)`. `useForm` is for forms; this is a one-shot delete with no payload. The standard tool is `router.delete`. Calling `useForm({})` triggers the `processing`/`errors` machinery for nothing.
  - **Source:** `resources/js/Pages/Admin/Groups/Index.vue:46`.
  - **Fix:** Replace with the direct call and track busy state with the local `editingId` ref:
    ```ts
    async function destroy(group) {
        if (!await confirm({ title: 'Delete group', message: `Delete group "${group.name}"? Members will be unassigned.`, confirmLabel: 'Delete', variant: 'danger' })) return;
        const id = group.id;
        deletingId.value = id;
        router.delete(`/groups/${id}`, {
            preserveScroll: true,
            onFinish: () => { deletingId.value = null; },
        });
    }
    ```
    And gate the button: `<VibeButton :disabled="deletingId === group.id" …>`.

- [ ] **FE-P2-42 · `SharedListing.vue` reimplements `iconFor()` locally with a smaller type list.**
  - **Problem:** `SharedListing.vue:228-236` defines a local `iconFor(type)` that covers 4 type buckets, while `lib/fileTypes.ts:45-52` has a fuller `iconFor()` already imported and used by `FileItem`, `QuickLookModal`, and `Storage`. Two sources of truth for "what icon does a file type get."
  - **Source:** `resources/js/Components/SharedListing.vue:228-236`, `resources/js/lib/fileTypes.ts:45-52`.
  - **Fix:** Import the shared helper and delete the local copy:
    ```ts
    import { iconFor } from '../lib/fileTypes';
    ```

- [ ] **FE-P2-43 · `SharedListing.vue` has a hand-rolled preview modal duplicating `QuickLookModal`.**
  - **Problem:** `SharedListing.vue:339-355` opens a `VibeModal` with an `<img>` or `<iframe>` — a strict subset of what `QuickLookModal.vue` does. Two preview modals, two keyboard handlers, two places to add PDF / video / markdown support. (See also FE-P0-25.)
  - **Source:** `resources/js/Components/SharedListing.vue:339-355`.
  - **Fix:** Mount the existing modal:
    ```vue
    <QuickLookModal v-model="previewOpen" :file="previewFile" :index="0" :total="1" :menu="[]" @step="previewOpen = false" />
    ```
    `QuickLookModal` already handles image / pdf / audio / video / markdown / office; remove the redundant `imageTypes` local and the `VibeModal` block.

- [ ] **FE-P2-44 · `Photos/Index.vue` lightbox uses `VibeCarousel` instead of `QuickLookModal`.**
  - **Problem:** `Photos/Index.vue:350-359` mounts `VibeCarousel` with a hand-rolled step / keyboard / filmstrip implementation, while `QuickLookModal.vue` already has a `prev` / `next` hover-peek, keyboard arrow handlers, and the same file shape. The two implementations have already drifted: QL supports markdown/office, Photos lightbox does not.
  - **Source:** `resources/js/Pages/Photos/Index.vue:323-385`, `resources/js/Components/QuickLookModal.vue`.
  - **Fix:** Replace the lightbox with `QuickLookModal` and feed it the photos list:
    ```ts
    const photosList = computed(() => props.photos);
    const { quickOpen, quickIndex, quickFile, open, step, close } = useQuickLook(photosList);
    ```
    ```vue
    <QuickLookModal v-model="quickOpen" :file="quickFile" :index="quickIndex" :total="photos.length"
                    :menu="photoMenu(quickFile)" :prev-file="prevFile" :next-file="nextFile"
                    @step="step" @action="onQuickAction" />
    ```
    Drop the in-modal `VibeCarousel` and the `lightboxOpen` ref. Keep the filmstrip (Photos-only) as a separate slot.

- [ ] **FE-P2-45 · `Files/Index.vue` reads `localStorage` at module-eval time (SSR crash).**
  - **Problem:** `Files/Index.vue:264-265` does `const viewMode = ref(localStorage.getItem('fm-view') === 'grid' ? 'grid' : 'list')` and `watch(viewMode, (v) => localStorage.setItem('fm-view', v))`. If the project ever enables SSR (Inertia SSR is on the roadmap), the initial render runs server-side where `localStorage` is `undefined`.
  - **Source:** `resources/js/Pages/Files/Index.vue:264-265`.
  - **Fix:** Defer the read to `onMounted`:
    ```ts
    const viewMode = ref<'list' | 'grid'>('list');
    onMounted(() => {
        const saved = localStorage.getItem('fm-view');
        if (saved === 'grid') viewMode.value = 'grid';
    });
    watch(viewMode, (v) => { if (typeof window !== 'undefined') localStorage.setItem('fm-view', v); });
    ```

- [ ] **FE-P2-46 · `Files/Index.vue` `?open=id` deep-link only opens QL for files, not folders.**
  - **Problem:** `Files/Index.vue:565-569` deep-links into a Quick Look when `?open=<id>` matches a file. The same parameter on a folder id is silently ignored — users sharing a folder URL see a stale listing. The `?folder=<id>` query is also accepted (`visitFolder`) so the two params are inconsistent.
  - **Source:** `resources/js/Pages/Files/Index.vue:75-77, 565-569`.
  - **Fix:** Route the deep-link through one function:
    ```ts
    onMounted(() => {
        const q = new URLSearchParams(window.location.search);
        const openId = q.get('open');
        if (!openId) return;
        const file = props.files.find((x) => String(x.id) === openId);
        const folder = props.folders.find((x) => String(x.id) === openId);
        if (file) quickLook(file);
        else if (folder) visitFolder(folder.id);
    });
    ```

- [ ] **FE-P2-47 · `Directory/Index.vue` lacks `LoadingSkeleton` / `PageError` like its siblings.**
  - **Problem:** `Photos`, `Storage`, `Shared`, `Trash`, and every Admin page mount `<LoadingSkeleton>` and `<PageError />`. `Directory/Index.vue:50-86` does neither — a navigation to `/directory` flashes blank while the request is in flight and server errors are silently invisible.
  - **Source:** `resources/js/Pages/Directory/Index.vue:50-86`.
  - **Fix:** Adopt the shared pattern:
    ```vue
    <script setup>
    import LoadingSkeleton from '../../Components/LoadingSkeleton.vue';
    import PageError from '../../Components/PageError.vue';
    const { loading } = usePageLoading();
    </script>
    <template>
        <AppLayout>
            <PageError />
            <PageHeader title="Directory" icon="person-rolodex" />
            <LoadingSkeleton v-if="loading" :rows="6" :cols="3" />
            <!-- existing filter row + groups, but in a <template v-else> -->
        </AppLayout>
    </template>
    ```

- [ ] **FE-P2-48 · `Photos/Index.vue` filter selects fire on every change — no debounce on `tag` filter.**
  - **Problem:** `Photos/Index.vue:218-230` calls `applyFilter({ tag: $event })` on every select change, hitting `/photos` immediately. A user scrolling through a long tag list triggers N requests.
  - **Source:** `resources/js/Pages/Photos/Index.vue:39-44, 218-230`.
  - **Fix:** Add an `album`/`tag` debounce path, mirroring Files' search debounce:
    ```ts
    let t: ReturnType<typeof setTimeout> | null = null;
    function applyFilterDebounced(patch: Record<string, unknown>) {
        if (t) clearTimeout(t);
        t = setTimeout(() => applyFilter(patch), 250);
    }
    ```
    Bind the tag dropdown to `applyFilterDebounced` and keep `applyFilter` (immediate) for actions that should fire on click.

- [ ] **FE-P2-49 · `inflight@1.0.6` is deprecated and leaks memory — `npm run build` warning.**
  - **Problem:** `npm warn deprecated inflight@1.0.6: This module is not supported, and leaks memory. Do not use it.` `inflight` is a transitive dependency of Vite's dev tooling. On a long-running dev server it leaks listeners.
  - **Source:** `npm ci` / `npm run build` warning output.
  - **Fix:** Wait for Vite to drop the dep, or override in `package.json`:
    ```json
    {
        "overrides": {
            "inflight": "npm:lru-cache@^10.0.0"
        }
    }
    ```
    Run `npm install` and re-test `npm run build`.

- [ ] **FE-P2-50 · `Files/Index.vue` size column hard-codes `(item.size / 1024).toFixed(1)` regardless of magnitude.**
  - **Problem:** `Files/Index.vue:366, 742` compute `"${(item.size / 1024).toFixed(2)} KB"` for the Details modal and `"${(item.size / 1024).toFixed(1)} KB"` for the table. A 2 GB file shows as `2097152.0 KB`. The shared `fmtBytes()` helper exists in `lib/format.ts` and already handles B/KB/MB/GB.
  - **Source:** `resources/js/Pages/Files/Index.vue:366, 742, 836`, `lib/format.ts` (`fmtBytes`).
  - **Fix:** Replace every inline `(x / 1024).toFixed(n)` with `fmtBytes(x)`:
    ```vue
    <td class="fw-medium font-monospace small">{{ fmtBytes(row.value) }}</td>
    ```
    And for the Details modal: `<tr v-for="row in detailsRows" :key="row.label"><th>{{ row.label }}</th><td>{{ row.formatted }}</td></tr>` where the row's `formatted` value is pre-formatted with `fmtBytes`.

- [ ] **FE-P2-51 · `Files/Index.vue` pluralization is wrong: `${item.item_count} item(s)`.**
  - **Problem:** `Files/Index.vue:742` and the Delete confirm message at line 551 use the bracketed `item(s)` workaround. The column shows `1 item(s)` for a single item, and the destructive-confirm shows `1 item(s)`.
  - **Source:** `resources/js/Pages/Files/Index.vue:742, 551`.
  - **Fix:** Extract a tiny `pluralize(n, singular, plural?)` helper and use it everywhere:
    ```ts
    // resources/js/lib/format.ts
    export function pluralize(n: number, singular: string, plural?: string) {
        return `${n.toLocaleString()} ${n === 1 ? singular : (plural ?? singular + 's')}`;
    }
    ```
    ```vue
    <td>{{ pluralize(item.item_count, 'item') }}</td>
    ```
    And: `` `Delete folder "${item.name}" and its ${pluralize(item.item_count, 'item')}?` ``.

- [ ] **FE-P2-52 · No `Cmd+Enter` to submit forms with textareas.**
  - **Problem:** `Files/Editor.vue:148` has a `<VibeFormTextarea v-model="note" :rows="3">` for the save-version note; `Profile/Edit.vue:176` has a `<VibeFormTextarea v-model="form.bio">` for the about section. In both, the user types into a multi-line field, then has to mouse over to the Save button. Apple/Google convention: `Cmd/Ctrl+Enter` submits the form.
  - **Source:** `resources/js/Pages/Files/Editor.vue:148, 154-156`, `resources/js/Pages/Profile/Edit.vue:176, 180-182`.
  - **Fix:** Add a global keydown listener on the form's root that intercepts `Cmd/Ctrl+Enter` and calls the submit handler:
    ```vue
    <script setup>
    function onKeydown(e: KeyboardEvent) {
        if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
            e.preventDefault();
            submit();
        }
    }
    </script>
    <template>
        <form @submit.prevent="submit" @keydown="onKeydown">…</form>
    </template>
    ```
    Or add it to `<AppFormGroup>` so every form picks it up automatically.

- [ ] **FE-P2-53 · Modal does not mark background as `inert` and may not trap focus.**
  - **Problem:** When a `VibeModal` is open, the page underneath remains in the tab order and screen-reader tree. Tab can escape the modal into the page below, and VoiceOver/NV will announce both layers. WCAG 2.1.2 (No Keyboard Trap) is the inverse problem; the standard modal pattern is **focus trap** + `inert` on the rest of the page.
  - **Source:** Every `<VibeModal>` consumer (Files/Index, Bookmarks, Vault, Photos, Admin/Groups, Admin/Backups, Notes, Errors).
  - **Fix:** Verify VibeUI's `VibeModal` does this; if not, wrap:
    ```vue
    <!-- resources/js/Components/AppModal.vue (additions to FE-P0-28) -->
    <script setup>
    import { useFocusTrap } from '@vueuse/integrations/useFocusTrap';
    const { activate, deactivate } = useFocusTrap(root, { immediate: false });
    watch(() => open.value, async (v) => {
        if (v) { await nextTick(); activate(); }
        else deactivate();
    });
    </script>
    <template>
        <div ref="root" class="modal-root">
            <slot />
        </div>
    </template>
    ```
    For `inert`, set `document.querySelector('#app > *:not(.modal-root)')?.setAttribute('inert', '')` on open and remove on close. (Requires a stable root id — add `<div id="app-content">` around the slot in `AppLayout.vue`.)

- [ ] **FE-P2-54 · Notes folder/rename flows use `prompt()` — no validation, no help text, no autocomplete.**
  - **Problem:** `Notes/Index.vue:60-64, 125-136` use the global `prompt()` (the dialog host in `AppLayout.vue:317-331`) for new folder name and rename. The dialog has a single input with no validation, no help text, no uniqueness check, no `autocomplete` attribute, and no preview of what the new name will look like. A user can submit an empty string (rejected client-side, line 62, 134), a name with only whitespace, or a duplicate.
  - **Source:** `resources/js/Pages/Notes/Index.vue:60-64, 125-136`, dialog host at `resources/js/Layouts/AppLayout.vue:317-331`.
  - **Fix:** Replace the `prompt()` calls with a proper in-page modal (or a dedicated `NotesPrompt` composable) that:
    - Renders `<AppFormGroup label="Folder name" required help-text="Names are case-sensitive and must be unique within the parent.">`.
    - Validates `name.trim().length > 0` and `name.length <= 255` before resolving.
    - Trims the result before returning.
    - Calls a server-side `GET /notes/folders?parent_id=X&q=…` for unique-suggestion feedback.
    ```vue
    <script setup>
    async function newFolder() {
        const name = await openPrompt({
            title: 'New folder',
            label: 'Folder name',
            placeholder: 'e.g. Projects',
            validate: (v) => v.trim().length > 0 ? null : 'Folder name is required.',
        });
        if (!name) return;
        router.post('/notes/folders', { name: name.trim(), parent_id: selectedFolder.value }, { preserveScroll: true });
    }
    </script>
    ```

- [ ] **FE-P2-55 · ShareModal grants accept only a single email — no multi-recipient.**
  - **Problem:** `ShareModal.vue:514` `<VibeFormInput v-model="grant.email" type="email" placeholder="person@example.com" />`. Pasting `alice@a.com, bob@b.com` submits the whole string as a single (invalid) email. Power users with 10 collaborators must submit the form 10 times.
  - **Source:** `resources/js/Components/ShareModal.vue:514, 426-440`.
  - **Fix:** Accept multiple addresses via a chip input (similar to Files Tags):
    ```vue
    <VibeFormGroup label="People" help-text="Paste a list of emails (comma- or newline-separated).">
        <VibeFormInput
            v-model="grant.emailInput"
            placeholder="alice@a.com, bob@b.com"
            @keyup.enter="addEmailChips"
        />
    </VibeFormGroup>
    <div v-if="grant.emails.length" class="d-flex flex-wrap gap-1 mt-2">
        <VibeBadge v-for="e in grant.emails" :key="e" variant="primary" class="d-flex align-items-center">
            {{ e }} <VibeIcon icon="x" class="ms-1" @click="grant.emails = grant.emails.filter((x) => x !== e)" />
        </VibeBadge>
    </div>
    ```
    Send `emails: string[]` to the backend (add `array` validation). Repeat for groups with a multi-select dropdown.

- [ ] **FE-P2-56 · UploadModal has no per-file size limit and one global progress bar.**
  - **Problem:** `UploadModal.vue:13-14, 911-983` validates the **total** selection against the quota but not the per-file `maxUploadKb`. A single 5 GB file passes the quota check, then fails the server 422 with a vague error. The single `<VibeProgress v-if="uploadForm.progress">` (line 1029-1033) shows one bar for 5 files — the user can't tell which file is currently uploading.
  - **Source:** `resources/js/Components/UploadModal.vue:911-983, 1029-1033`.
  - **Fix:** Add per-file pre-check and per-file progress:
    ```ts
    const oversize = computed(() => uploadFiles.value.find((f) => f.size > props.maxUploadKb * 1024));
    // Block submit when oversize.value exists; show inline error naming the file.
    ```
    For progress, switch to a per-row progress bar:
    ```vue
    <div v-for="(f, i) in uploadFiles" :key="`${f.name}-${f.size}-${f.lastModified}`" class="d-flex align-items-center gap-2 p-2 border-bottom">
        <img v-if="blobUrlFor(f)" :src="blobUrlFor(f)!" … />
        <div class="flex-grow-1 min-w-0">
            <div class="text-truncate small">{{ f.name }}</div>
            <VibeProgress v-if="uploadProgress[i]" :bars="[{ value: uploadProgress[i], showValue: true }]" />
        </div>
    </div>
    ```
    Track per-file `XMLHttpRequest.upload.onprogress` and abort the upload on file-level size-violation.

- [ ] **FE-P2-57 · Admin can toggle `is_admin` off for their own row with no confirmation.**
  - **Problem:** `Admin/Users/Edit.vue:61` `<VibeFormSwitch v-model="form.is_admin" label="Administrator">`. An admin editing their own profile can flip this off, save, and the next request 403s. No warning, no confirm, no recovery.
  - **Source:** `resources/js/Pages/Admin/Users/Edit.vue:61`, `submit()` at line 22-26.
  - **Fix:** Add a guard in `submit()` and a runtime warning in the template:
    ```ts
    async function submit() {
        if (props.user.id === usePage().props.auth?.user?.id && props.user.is_admin && !form.is_admin) {
            if (!await confirm({ title: 'Demote yourself?', message: 'You are about to remove your own admin privileges. You will be signed out and unable to manage users. Continue?', confirmLabel: 'Yes, demote me', variant: 'danger' })) return;
        }
        form.patch(`/admin/users/${props.user.id}`, { onFinish: () => form.reset('password', 'password_confirmation') });
    }
    ```
    Also hide the switch for the `id === me` case (cleaner UX).

- [ ] **FE-P2-58 · Files Tags modal accepts an unbounded number of tags with no dedup or length limit.**
  - **Problem:** `Files/Index.vue:497-522` `tagList` is a `ref<string[]>`, `tagInput` is a free-text string. No `maxlength` on the underlying input. No dedup warning. No max count. A user can add 200 tags (one char each) and the server may reject the request with a 422 — the modal has no error display for tag-list errors.
  - **Source:** `resources/js/Pages/Files/Index.vue:497-540`.
  - **Fix:** Bound the input + the list:
    ```vue
    <VibeFormInput v-model="tagInput" :maxlength="40" @keyup.enter="addTag()" />
    ```
    ```ts
    const MAX_TAGS = 50;
    function addTag(name?: string) {
        const value = (name ?? tagInput.value).trim();
        if (!value || value.length > 40) return;
        if (tagList.value.length >= MAX_TAGS) { tagListError.value = `A file can have at most ${MAX_TAGS} tags.`; return; }
        if (tagList.value.includes(value)) { tagListError.value = `"${value}" is already added.`; return; }
        tagList.value.push(value);
        tagInput.value = '';
        tagListError.value = '';
    }
    ```
    Render the inline error under the chip list. Also send the tag list to the backend with `array|max:50` validation.

- [ ] **FE-P2-59 · Public share unlock has no client-side brute-force UX feedback.**
  - **Problem:** `Public/Share.vue:21-24` POSTs the password with no rate limit. The backend has protection (BE-P0-2), but if the backend starts returning `429`, the client shows the same generic "Could not unlock" / `form.errors.password` text — no retry-after hint, no lockout countdown. Worse, on the 5th try the form just silently fails.
  - **Source:** `resources/js/Pages/Public/Share.vue:21-24`, `form.errors` rendering at line 35.
  - **Fix:** Surface rate-limit feedback:
    ```ts
    function unlock() {
        form.post(`/s/${props.token}/unlock`, {
            onSuccess: () => form.reset('password'),
            onError: (e) => {
                if (e?.response?.status === 429) {
                    const retryAfter = Number(e.response.headers.get('Retry-After')) || 60;
                    rateLimitMessage.value = `Too many attempts. Try again in ${retryAfter}s.`;
                }
            },
        });
    }
    ```
    Render the message in a `VibeAlert variant="warning"` above the form. Also disable the submit button for `retryAfter` seconds client-side as a progressive enhancement.

- [ ] **FE-P2-60 · `Files/Editor.vue` Save stays enabled when the editor throws — user clicks Save on a broken editor and gets a silent error.**
  - **Problem:** `Files/Editor.vue:104` `:disabled="!ready || kind === 'unsupported' || !!loadError"`. The `loadError` ref is set on `@error` from the child editor (line 121, 128) and on `serialize()` failure (line 82). But the `serialize()` catch in `commitSave()` only fires when the user clicks Save; until then the error is hidden. If the editor has partially loaded (ready=true) but throws on serialize, the user clicks Save, hits the catch at line 80, and gets a generic `Could not save this document.` alert — no field-level feedback.
  - **Source:** `resources/js/Pages/Files/Editor.vue:55-84, 104, 111-133`.
  - **Fix:** Surface the per-attempt error inline:
    ```ts
    async function commitSave() {
        if (!ready.value || loadError.value || typeof editorRef.value?.serialize !== 'function') return;
        saving.value = true;
        try {
            const blob = await editorRef.value.serialize();
            if (!blob) throw new Error('Editor returned an empty document.');
            // … submit …
        } catch (e: any) {
            saving.value = false;
            loadError.value = e?.message || 'Could not save this document.';
        }
    }
    ```
    And render a dismissible `VibeAlert variant="danger" dismissible` near the Save button (not just at the top of the surface), so the user sees the error in the same place as the action that caused it.

- [ ] **FE-P2-61 · Storage analyzer treemap recalculates layout on every prop or resize update.**
  - **Problem:** The storage page rebuilds the tree and squarified layout from scratch on every reactive change, recreating thousands of DOM tiles and causing jank with large datasets.
  - **Source:** `resources/js/Pages/Storage/Index.vue`.
  - **Fix:** Keep raw nodes in `shallowRef`/`markRaw`, memoize the layout computation, and cap the maximum tile count.

- [ ] **FE-P2-62 · EditorModal hides content load failures.**
  - **Problem:** When file content fails to load, the editor still opens and shows an empty editor with no error feedback.
  - **Source:** `resources/js/Components/EditorModal.vue`.
  - **Fix:** Catch the load error, set an error state, and render an inline alert instead of the editor surface.

- [ ] **FE-P2-63 · Star/move/delete actions lack optimistic UI and busy guards.**
  - **Problem:** Star actions wait for the server round-trip before updating, and move/delete triggers stay enabled during the request, allowing double submissions.
  - **Source:** `resources/js/Pages/Files/Index.vue`, `resources/js/Pages/Photos/Index.vue`.
  - **Fix:** Toggle the star optimistically with a rollback on error, and wrap move/delete handlers in a busy guard that disables the trigger until the request finishes.

- [ ] **FE-P2-64 · No global Vue error boundary or handler.**
  - **Problem:** Unhandled runtime errors leave users staring at a blank or partially rendered page with no recovery affordance.
  - **Source:** `resources/js/app.js`.
  - **Fix:** Add `app.config.errorHandler` and mount a fallback error boundary component that shows a friendly message and a reload action.

- [ ] **FE-P2-65 · Preview modals lack loading and error states.**
  - **Problem:** Images and iframes in Quick Look show empty space while loading and a browser broken-image icon when they fail.
  - **Source:** `resources/js/Components/QuickLookModal.vue`, `resources/js/Components/SharedListing.vue`.
  - **Fix:** Add `load` and `error` event handlers, show a spinner during load, and render a "Preview unavailable" alert on failure.

- [ ] **FE-P2-66 · FileItem inside the list view is missing a stable `:key`.**
  - **Problem:** Although the data table has a row key, the `FileItem` component rendered in the slot has no key, which can cause stale selection or checkbox state when rows update.
  - **Source:** `resources/js/Pages/Files/Index.vue`.
  - **Fix:** Add `:key="item._key"` to the `FileItem` instance inside the list slot.

- [ ] **FE-P2-67 · Trash restore/purge/empty lack processing state and undo.**
  - **Problem:** Trash action buttons remain clickable during the request, and accidental restore/purge cannot be undone.
  - **Source:** `resources/js/Pages/Trash/Index.vue`.
  - **Fix:** Track row-level busy states, disable action buttons while processing, and show a success toast with a temporary Undo action.

### FE-P3 — Polish

- [ ] **FE-P3-16 · Upload modal queue uses duplicate `:key` values.**
  - **Problem:** File queue rows are keyed by index; reordering/removing causes jumpy transitions and stale row state.
  - **Source:** `resources/js/components/UploadModal.vue:110`.
  - **Fix:** Generate a unique upload id per file and use it as `key`.

- [ ] **FE-P3-17 · QuickLook download link can be `null`.**
  - **Problem:** The `href` is computed from `currentFile.download_url`, which may be missing; an empty link confuses screen readers and users.
  - **Source:** `resources/js/components/QuickLook.vue:55`.
  - **Fix:** Hide the download button when `download_url` is absent.

- [ ] **FE-P3-18 · Details panel size is hard-coded to kilobytes.**
  - **Problem:** `formatSize` always divides by 1024 regardless of magnitude; large files display unreadable KB numbers.
  - **Source:** `resources/js/composables/useFormat.ts`.
  - **Fix:** Use dynamic units (B, KB, MB, GB).

- [ ] **FE-P3-19 · `openTransfer` detects folders via object shape, which is brittle.**
  - **Problem:** It checks `selected.value.every((f) => f.children !== undefined)`, but the backend already returns `is_dir`. False positives can move files as folders.
  - **Source:** `resources/js/Pages/Files/Index.vue:310`.
  - **Fix:** Use `item.is_dir` explicitly.

- [ ] **FE-P3-20 · `useJobPolling` never runs an immediate first check.**
  - **Problem:** It waits for the first interval before reporting status, so fast jobs feel slow.
  - **Source:** `resources/js/composables/useJobPolling.ts`.
  - **Fix:** Invoke the poll function immediately before starting the interval.

- [ ] **FE-P3-21 · Backups polling never stops after leaving the page.**
  - **Problem:** `setInterval` is not cleared on unmount, leaking intervals and HTTP requests.
  - **Source:** `resources/js/Pages/Admin/Backups/Index.vue:40`.
  - **Fix:** Store the interval id and clear it in `onBeforeUnmount`.

- [ ] **FE-P3-22 · Generic `v-for` keyed by array index.**
  - **Problem:** Several lists use `:key="index"`, causing inefficient re-renders when items are inserted/removed.
  - **Source:** Various tables and lists.
  - **Fix:** Prefer stable object ids; fall back to `index` only for static lists.

- [ ] **FE-P3-23 · Guest layout still hard-codes app name.**
  - **Problem:** The login/register layout shows raw text instead of reading from config.
  - **Source:** `resources/js/Layouts/GuestLayout.vue:12`.
  - **Fix:** Use `usePage().props.appName`.

- [ ] **FE-P3-24 · Global `Cmd+K` shortcut hijacks input fields.**
  - **Problem:** The command palette listener does not check `event.target` and opens while typing in inputs.
  - **Source:** `resources/js/composables/useCommandPalette.ts`.
  - **Fix:** Ignore events whose target is an `<input>`, `<textarea>`, or `[contenteditable]`.

- [ ] **FE-P3-25 · Grid pagination resets current page on refresh.**
  - **Problem:** Inertia visits with `preserveState: true` still reset `page` because the URL param is not preserved.
  - **Source:** `resources/js/Pages/Files/Index.vue:200-210`.
  - **Fix:** Include `page` in preserveState logic and derive from `props.filters.page`.

- [ ] **FE-P3-26 · ShareModal external link lacks `rel="noopener"`.**
  - **Problem:** Generated public share links open with `target="_blank"` but no `rel` attribute.
  - **Source:** `resources/js/components/ShareModal.vue:140`.
  - **Fix:** Add `rel="noopener noreferrer"`.

- [ ] **FE-P3-27 · Props style inconsistent across components.**
  - **Problem:** Mix of runtime `defineProps({})` and TS `defineProps<...>()` across the codebase.
  - **Source:** Many components.
  - **Fix:** Standardise on TS-style props; use runtime validators only where runtime data quality matters.

- [ ] **FE-P3-28 · Heavy reactive objects could use `shallowRef`.**
  - **Problem:** File/folder lists are deeply reactive even though individual properties are read-only after fetch.
  - **Source:** `resources/js/Pages/Files/Index.vue`.
  - **Fix:** Convert read-only bulk data to `shallowRef` to reduce reactivity overhead.

- [ ] **FE-P3-29 · Markdown outline re-splits heading text on every scroll.**
  - **Problem:** Outline headings are re-parsed inside a scroll listener instead of once at mount.
  - **Source:** `resources/js/components/MarkdownOutline.vue:30`.
  - **Fix:** Compute `headings` once and throttle scroll handler.

- [ ] **FE-P3-30 · Sidebar group headings use wrong semantic heading levels.**
  - **Problem:** Group labels are rendered as `h3` inside a nav region where the page title is `h1`, causing an outline gap.
  - **Source:** `resources/js/Layouts/AuthenticatedLayout.vue`.
  - **Fix:** Use `h2` for top-level nav groups.

- [ ] **FE-P3-31 · Bookmark card uses stretched link without focus boundary.**
  - **Problem:** The entire card is clickable via `stretched-link`, but focus outline only wraps the title link, hiding keyboard focus from users.
  - **Source:** `resources/js/Pages/Bookmarks/Index.vue:90`.
  - **Fix:** Make the card itself focusable or move the link to wrap the whole card with visible focus.

- [ ] **FE-P3-32 · Bookmark start date uses raw format.**
  - **Problem:** `started_at` is rendered as the ISO string from the database instead of a locale date.
  - **Source:** `resources/js/Pages/Bookmarks/Index.vue:95`.
  - **Fix:** Use `new Date(item.started_at).toLocaleDateString()` or a shared formatter.

- [ ] **FE-P3-33 · "Remove" vs "Delete" vocabulary drift across surfaces.**
  - **Problem:** The same destructive action is named two different ways. Bookmarks/Notes/Vault use **Remove** (`Bookmarks/Index.vue:107, 281`, `Notes/Index.vue` rename affordances, `Vault/Index.vue:94, 155`); Files/Photos/Trash/Admin use **Delete** (`Files/Index.vue:286, 553`, `Photos/Index.vue:141, 153, 274`, `Trash/Index.vue:33, 39, 75`, `Admin/Groups/Index.vue:96`). The toast / undo button (FE-P0-26) and the bulk-action bar will need one label.
  - **Source:** See file:line references above.
  - **Fix:** Pick one. Apple uses **Delete** for permanent, **Remove** for reversible. Silo supports both: Trash is reversible → **Remove**; permanent flows → **Delete**. Document the rule in `docs/copy.md` and grep-replace:
    - Trash (`Trash/Index.vue`): "Purge" for permanent, "Restore" for reversible. Already correct.
    - Soft-delete (Files/Photos/Bookmarks/Notes/Vault → Trash): "Move to Trash" for the action label, **Delete** for the confirm button (it's still a delete from the user's perspective).
    - All Vault row actions: **Delete** (no Trash integration for secrets).
    - Notes: rename → already "Rename" (not a delete), no change. Bookmark folder "Delete" (cascade).
    Update every `confirmLabel: 'Remove'` → `confirmLabel: 'Delete'` and every button text accordingly.

- [ ] **FE-P3-34 · "Open" affordance uses 4 different icons for 4 different intents.**
  - **Problem:** Same verb, four glyphs: Photos uses `arrows-fullscreen` (lightbox, `Photos/Index.vue:137`); Bookmarks uses `box-arrow-up-right` (new tab, `Bookmarks/Index.vue:262, 276`); Shared uses `box-arrow-in-right` (browse, `SharedListing.vue:289`); Files QuickLook uses the quick-look `eye` (hover, `SharedListing.vue:330`). No way to tell from a screenshot what "Open" does.
  - **Source:** `Photos/Index.vue:137`, `Bookmarks/Index.vue:262, 276`, `SharedListing.vue:289, 330`, `Files/Index.vue:284`.
  - **Fix:** Build an `openAction(item)` map keyed by file type:
    ```ts
    // resources/js/lib/openIntent.ts
    export type OpenIntent = 'quick-look' | 'lightbox' | 'new-tab' | 'browse' | 'edit' | 'reveal';
    export function openIntent(item: { is_dir?: boolean; type?: string }): OpenIntent {
        if (item.is_dir) return 'browse';
        const t = item.type ?? '';
        if (['jpg', 'jpeg', 'png', 'gif', 'webp', 'heic'].includes(t)) return 'lightbox';
        if (t === 'pdf' || t === 'md' || ['doc', 'docx', 'xls', 'xlsx', 'csv', 'ods', 'txt'].includes(t)) return 'quick-look';
        if (t === 'url' || t === 'bookmark') return 'new-tab';
        return 'quick-look';
    }
    ```
    Then every menu item uses the intent for both icon and tooltip.

- [ ] **FE-P3-35 · Cancel button variant is inconsistent across modals — pick one.**
  - **Problem:** Cancel buttons render as `outline` secondary in some modals, `light` in others, plain `secondary` in others. Examples: `RenameModal.vue:209` uses `outline`; `Vault/Index.vue:183, 320` uses `outline`; `Photos/Index.vue:392, 403, 416` uses `outline`; `Files/Editor.vue:152` uses `light`; `Bookmarks/Index.vue:320` uses `outline`; `Admin/Groups/Index.vue:86` uses `outline`; the `SpreadsheetEditor` cancel uses `outline`. One outlier: `EditorModal.vue` uses `outline`; `ShareModal.vue:565` uses `outline`. Pick a rule.
  - **Source:** See references above.
  - **Fix:** Establish the design rule: **Cancel = `variant="light"`** (no outline, lower visual weight than the primary action). **Close = `variant="secondary" outline`** (for "X" buttons in a header). Audit all modals, apply the rule, drop the inconsistency.

- [ ] **FE-P3-36 · Sidebar group headings skip heading levels — `<h6>` after `<h1>` is an a11y outline gap.**
  - **Problem:** `AppLayout.vue:234, 244, 264` renders sidebar group labels ("Admin", "Smart Folders", "Storage") as `<div class="side-heading">` (no semantic level at all). The page title is `<h1>` (after FE-P0-21) and section headings inside the main column are `<h2>`. Screen readers see an outline gap.
  - **Source:** `resources/js/Layouts/AppLayout.vue:234, 244, 264`.
  - **Fix:** Make the sidebar group heading a real, scoped heading element:
    ```vue
    <h2 class="side-heading h6 text-uppercase text-muted mb-2">
        <VibeIcon icon="shield-lock-fill" class="me-1" />Admin
    </h2>
    ```
    Use `<h2>` consistently; the `h6` is purely visual sizing (Bootstrap utility).

- [ ] **FE-P3-37 · `Shared/Folder.vue` breadcrumb payload shape differs from `Files/Index.vue` breadcrumb.**
  - **Problem:** `Shared/Folder.vue:19-23` builds crumbs as `{ text, folder, active }`; `Files/Index.vue:66-73` builds as `{ text, folder, active }` too — same shape! — but `AppLayout.vue:108-130` builds the sidebar nav as `{ text, href, icon, active }` with **no `folder` field**. `VibeBreadcrumb` therefore needs a different `item-click` payload on the sidebar (it gets `{ item }` with `href`) than the page breadcrumb (it gets `{ item }` with `folder`). Two conventions, one component, one bug surface.
  - **Source:** `Shared/Folder.vue:19-23, 25-28`, `Files/Index.vue:66-73, 79-81`, `AppLayout.vue:108-130, 138-142`.
  - **Fix:** Unify the breadcrumb payload to a discriminated union and use it everywhere:
    ```ts
    type Crumb = { text: string; icon?: string } & ({ href: string; active?: never } | { folder: number | null; active?: boolean });
    ```
    Make `VibeBreadcrumb` emit a typed `crumb-click` event with the original `Crumb` payload; consumers do `if ('href' in c) router.visit(c.href); else visitFolder(c.folder);`.

- [ ] **FE-P3-38 · Bookmarks "New folder" action is misleading — opens the Add Bookmark modal pre-filled.**
  - **Problem:** `Bookmarks/Index.vue:115-120` `addFolder()` sets `selectedFolder.value = name.trim(); openAdd();`. The user sees the **Add Bookmark** modal with the folder name in the `category` field. There is no folder created. The toolbar `+` button (`Bookmarks/Index.vue:212-214`) does the same thing.
  - **Source:** `resources/js/Pages/Bookmarks/Index.vue:115-120, 158, 212-214`.
  - **Fix:** Create the folder, then optionally open the Add modal:
    ```ts
    async function addFolder() {
        const name = await prompt({ title: 'New folder', message: 'Folder name:', confirmLabel: 'Create' });
        if (!name || !name.trim()) return;
        router.post('/bookmarks/folders', { name: name.trim() }, {
            preserveScroll: true,
            onSuccess: () => { selectedFolder.value = name.trim(); },
        });
    }
    ```
    Add the `POST /bookmarks/folders` endpoint on the backend (or use a generic `category` upsert). Also add a visual separator + dedicated label for the folder-creation button in the toolbar (e.g. a `VibeDropdown` item "New folder" alongside "New bookmark").

- [ ] **FE-P3-39 · Bookmarks has no multi-select / bulk actions (see FE-P0-22 for the surface-level fix).**
  - **Problem:** Bookmarks/Index.vue has 300+ lines, no `useSelection` import, no checkbox column, no bulk-action bar. A power user with 200 bookmarks must delete one at a time.
  - **Source:** `resources/js/Pages/Bookmarks/Index.vue` (whole file).
  - **Fix:** Adopt the `useSelection` + `BatchActions` pattern (FE-P0-22). Add a checkbox column to the list view, gate the existing action bar on `selectedItems.length > 0`, support bulk "Move to folder", "Delete", "Refresh health check".

- [ ] **FE-P3-40 · Vault `copy()` is silent (no feedback).**
  - **Problem:** `Vault/Index.vue:56-58` writes to clipboard with no UI confirmation. The user can't tell if the copy succeeded.
  - **Source:** `resources/js/Pages/Vault/Index.vue:56-58`.
  - **Fix:** See FE-P1-40.

- [ ] **FE-P3-41 · Directory page search input is not debounced — filter on every keystroke.**
  - **Problem:** `Directory/Index.vue:15-20` runs the full filter on every keystroke of `search`. The department dropdown fires immediately on change (line 60), but search is unbounded. With 1,000 users the browser recomputes `filtered.value` 200 times while the user types "an".
  - **Source:** `resources/js/Pages/Directory/Index.vue:12-20, 57-60`.
  - **Fix:** Debounce the search input by 250 ms:
    ```ts
    const search = ref('');
    const debouncedSearch = refDebounced(search, 250);
    const filtered = computed(() => /* uses debouncedSearch.value */);
    ```
    Or use `watchDebounced` from `@vueuse/core` if already a dep (if not, install only the function you need — `@vueuse/core` is too heavy for a single helper; write the 6-line debounce inline).

- [ ] **FE-P3-42 · `Files/Editor.vue` `newName` ref is initialised empty for the edit path.**
  - **Problem:** `Files/Editor.vue:43` `const newName = ref(props.create?.name || '')` — when editing an existing file, `newName` is `''`. `openSave()` (line 50-53) sets it to `docName.value` only when the user clicks Save. If the user hits ⌘S without opening the popup (or the popup is dismissed), the file would be uploaded with the empty name.
  - **Source:** `resources/js/Pages/Files/Editor.vue:43, 50-53, 68-72`.
  - **Fix:** Seed `newName` with the document name from the start:
    ```ts
    const newName = ref(props.create?.name || props.file?.name || '');
    ```
    And add a `keyup.ctrl.s` / `keydown.meta.s` shortcut to call `commitSave()` directly when the popup is closed (FE-P4 enhancement; the basic fix is the seed).

- [ ] **FE-P3-43 · "Manage storage" link points at `/usage` — route may not exist.**
  - **Problem:** `AppLayout.vue:271` renders `<a href="/usage" @click.prevent="router.visit('/usage')">`. No `Pages/Usage/Index.vue` exists; the actual storage page is `Storage/Index.vue` at `/storage`. Clicking the link 404s.
  - **Source:** `resources/js/Layouts/AppLayout.vue:271`.
  - **Fix:** Either rename the route to `/storage` or add a `Pages/Usage/Index.vue` that wraps the storage page with a different chrome. Recommended: change the link to `href="/storage"`.

- [ ] **FE-P3-44 · `Bookmarks/Index.vue` `failedIcons` ref churns on every add.**
  - **Problem:** `Bookmarks/Index.vue:70-75` wraps a `Set` in a `ref` and reassigns on every add. Each add allocates a new `Set` plus triggers a full reactivity round-trip for the wrapped ref. With 200 bookmarks and the user hovering 50, the GC pressure is real.
  - **Source:** `resources/js/Pages/Bookmarks/Index.vue:70-75`.
  - **Fix:** Use `shallowRef` for read-only bulk data and mutate in place:
    ```ts
    const failedIcons = shallowRef(new Set<number>());
    function onIconError(id: number) {
        failedIcons.value.add(id);
        // shallowRef: trigger once for the .value assignment
        failedIcons.value = failedIcons.value;
    }
    ```
    Or, cleaner: use a `Map<number, true>` and trigger via `triggerRef(failedIcons)` if you want fine-grained control.

- [ ] **FE-P3-45 · `Photos/Index.vue` keyboard handler captures `Space`/`Arrows` only when lightbox is open.**
  - **Problem:** `Photos/Index.vue:108-115` installs `onLightboxKey` globally; it correctly bails when an input is focused, but `Space` (which is a common "select" / "play" key in a gallery) does nothing. Meanwhile `Files/Index.vue:399-422` uses Space to open QL.
  - **Source:** `resources/js/Pages/Photos/Index.vue:108-117`.
  - **Fix:** Mirror Files/Index: when the lightbox is closed, `Space` toggles select mode for the active cell; when open, `Space` closes:
    ```ts
    function onLightboxKey(e) {
        const inField = ['input', 'textarea', 'select'].includes((e.target?.tagName || '').toLowerCase()) || e.target?.isContentEditable;
        if (inField) return;
        if (!lightboxOpen.value) {
            if (e.key === ' ') { e.preventDefault(); selectMode.value = !selectMode.value; }
            return;
        }
        if (e.key === 'ArrowRight' || e.key === 'ArrowDown') { e.preventDefault(); step(1); }
        else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') { e.preventDefault(); step(-1); }
        else if (e.key === 'Escape') lightboxOpen.value = false;
        else if (e.key === ' ') { e.preventDefault(); lightboxOpen.value = false; }
    }
    ```

- [ ] **FE-P3-46 · No "Save and Add Another" pattern on bulk-create flows.**
  - **Problem:** Bookmarks, Vault, Photos album, Notes folder, Admin/Groups, Files folder — every create flow closes the modal on save. A user with 5 bookmarks to enter clicks "Add" 5 times, re-types the title 5 times, and re-enters the folder 5 times. Apple and Google both have a "Save and Add Another" affordance for bulk-create.
  - **Source:** Every `save()` / `onSuccess` in create modals: `Bookmarks/Index.vue:101-104`, `Vault/Index.vue:83-87`, `Photos/Index.vue:160-162, 166-169`, `Notes/Index.vue:99-101`, `Admin/Groups/Index.vue:27-29`, `Files/Index.vue:431-439`.
  - **Fix:** Add a `bulkCreate` flag to `ResourceModal` (REF-P1-02) and a third footer button:
    ```vue
    <template #footer>
        <VibeButton variant="secondary" outline @click="open = false">Cancel</VibeButton>
        <VibeButton variant="primary" outline v-if="bulkCreate" :disabled="form.processing" @click="save({ andAnother: true })">
            <VibeIcon icon="plus-lg" class="me-1" />Save and add another
        </VibeButton>
        <VibeButton variant="primary" :disabled="form.processing" @click="save()">Create</VibeButton>
    </template>
    ```
    In `save()`:
    ```ts
    function save(opts: { andAnother?: boolean } = {}) {
        const handler = editingId.value
            ? (cb) => form.put(`/path/${editingId.value}`, cb)
            : (cb) => form.post('/path', cb);
        handler({
            preserveScroll: true,
            onSuccess: () => {
                if (opts.andAnother) { form.reset(); editingId.value = null; toast.push('Saved. Add another.', { variant: 'success' }); return; }
                open.value = false;
            },
        });
    }
    ```
    Show the third button only on create (not edit), and only when the entity is "additive" (bookmark, secret, album, folder, group).

- [ ] **FE-P3-47 · No "focus first invalid field" on submit-with-errors.**
  - **Problem:** When a form fails validation, the error messages render in their respective fields, but **no JavaScript moves focus to the first invalid field**. WCAG 3.3.1 and a common Apple/Google pattern: after a failed submit, focus + scroll the first invalid input so the user is taken directly to the problem.
  - **Source:** Every form's `submit()` / `onError` / `onFinish` handler: `Auth/Login.vue:8`, `Auth/Register.vue:8`, `Auth/ResetPassword.vue:18`, `Auth/ConfirmPassword.vue:8`, `Profile/Edit.vue:34`, `Admin/Users/Edit.vue:23`, `Bookmarks/Index.vue:101`, `Vault/Index.vue:84`, `Photos/Index.vue:161, 168`, `Files/Editor.vue:55`.
  - **Fix:** Add to the form's submit handler (or globally in `AppForm` / a shared util):
    ```ts
    function focusFirstError(errors: Record<string, string>) {
        const firstKey = Object.keys(errors).find((k) => errors[k]);
        if (!firstKey) return;
        nextTick(() => {
            const el = document.getElementById(`field-${firstKey}`) as HTMLElement | null;
            el?.focus();
            el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    }
    // in submit:
    form.post('/path', { onError: () => focusFirstError(form.errors) });
    ```
    Each `AppFormGroup` must render its input with `:id="\`field-${key}\`"` (see FE-P0-29).

- [ ] **FE-P3-48 · Revealed Vault secret uses `user-select: all` which doesn't trigger the iOS copy toolbar.**
  - **Problem:** `Vault/Index.vue:142` `<code class="vault-secret">{{ revealed[item.id] }}</code>` + the CSS at `Vault/Index.vue:192-193` `.vault-secret { user-select: all; }`. On iOS Safari, `user-select: all` on a non-form element does not show the floating copy toolbar — users have to long-press, drag handles, and manually select. The dedicated **Copy** button exists but is the only reliable path; sighted users don't know they need it.
  - **Source:** `resources/js/Pages/Vault/Index.vue:142, 192-193`.
  - **Fix:** Render the secret in a selectable input (so iOS shows the native toolbar) and keep the Copy button as the primary affordance:
    ```vue
    <input
        v-if="revealed[item.id]"
        class="form-control form-control-sm vault-secret"
        :value="revealed[item.id]"
        readonly
        @focus="$event.target.select()"
    >
    ```
    And drop the `user-select: all` CSS. Optionally keep the `<code>` for desktop as a fallback.

- [ ] **FE-P3-49 · No form-state preservation across navigations — typing into a form, then clicking a sidebar link, discards all input.**
  - **Problem:** Apple and Google both preserve form drafts: a user types a long bookmark description, accidentally clicks "Files" in the sidebar, comes back, and the description is gone. Inertia does not preserve form state across full navigations, and the codebase has no draft-saving utility.
  - **Source:** All multi-field forms (Bookmarks add/edit, Vault add/edit, Files rename, Profile edit, etc.).
  - **Fix:** Add a `useFormDraft` composable that mirrors `form.data()` to `localStorage` on every change, and restores on mount if the form is empty:
    ```ts
    // resources/js/composables/useFormDraft.ts
    export function useFormDraft(form: ReturnType<typeof useForm>, key: string) {
        const STORAGE = `draft:${key}`;
        onMounted(() => {
            const saved = localStorage.getItem(STORAGE);
            if (saved) try { Object.assign(form, JSON.parse(saved)); } catch {}
        });
        watch(() => ({ ...form.data() }), (v) => localStorage.setItem(STORAGE, JSON.stringify(v)), { deep: true });
        watch(() => form.recentlySuccessful, (ok) => { if (ok) localStorage.removeItem(STORAGE); });
    }
    ```
    Adopt on the long forms: Bookmarks add/edit, Vault add/edit, Files rename, Profile edit. Show a "Draft restored" toast on mount.

- [ ] **FE-P3-50 · `ForgotPassword` has no client-side rate-limit feedback — user can click 100 times.**
  - **Problem:** `Auth/ForgotPassword.vue:9-11` `form.post('/password/email')` fires on every click. No client-side throttle. The backend will throttle, but the UX gives no feedback ("check your email" is shown even if the server 429s).
  - **Source:** `resources/js/Pages/Auth/ForgotPassword.vue:9-11, 16-17`.
  - **Fix:** Disable the button for 30 s after a successful send + show a countdown:
    ```ts
    const cooldown = ref(0);
    let cooldownTimer: ReturnType<typeof setInterval> | null = null;
    function submit() {
        if (cooldown.value > 0) return;
        form.post('/password/email', {
            onSuccess: () => {
                cooldown.value = 30;
                cooldownTimer = setInterval(() => {
                    cooldown.value -= 1;
                    if (cooldown.value <= 0 && cooldownTimer) { clearInterval(cooldownTimer); cooldownTimer = null; }
                }, 1000);
            },
        });
    }
    onBeforeUnmount(() => { if (cooldownTimer) clearInterval(cooldownTimer); });
    ```
    Render `<VibeButton :disabled="form.processing || cooldown > 0">{{ cooldown > 0 ? `Wait ${cooldown}s` : 'Send reset link' }}</VibeButton>`.

- [ ] **FE-P3-51 · Bookmark `description` field has no character limit or counter — user can paste 10 MB.**
  - **Problem:** `Bookmarks/Index.vue:300-302` `<VibeFormInput v-model="form.description" placeholder="Optional">`. No `maxlength`, no counter, no backend limit surfaced client-side. Pasting a 10 MB blob silently hits the server 422.
  - **Source:** `resources/js/Pages/Bookmarks/Index.vue:300-302`.
  - **Fix:** Bound the input + show a counter:
    ```vue
    <VibeFormGroup label="Description" help-text="Up to 500 characters.">
        <VibeFormInput v-model="form.description" :maxlength="500" />
        <div class="small text-muted text-end" :class="{ 'text-danger': form.description.length > 480 }">
            {{ form.description.length }} / 500
        </div>
    </VibeFormGroup>
    ```
    Add `max:500` validation server-side. Apply the same pattern to the other unbounded text fields: Notes folder name (255), Files tag name (40 in FE-P2-58), Vault notes (1000), Profile bio (2000).

- [ ] **FE-P3-42 · Images in QuickLook and SharedListing lack dimensions and lazy loading.**
  - **Problem:** Preview thumbnails and images have no explicit width/height, causing layout shift, and large previews download eagerly even when off-screen.
  - **Source:** `resources/js/Components/QuickLookModal.vue`, `resources/js/Components/SharedListing.vue`.
  - **Fix:** Add `width`/`height` attributes to images and use `loading="lazy"` for non-critical previews.

- [ ] **FE-P3-43 · NotesSidebar tree chevrons lack an accessible expanded state.**
  - **Problem:** The folder/notebook expand/collapse chevrons are icon-only and do not expose `aria-expanded` or `aria-controls`.
  - **Source:** `resources/js/Components/Notes/NotesSidebar.vue`.
  - **Fix:** Convert each chevron to a `<button>` with `aria-expanded` and `aria-controls` pointing to the child list container.

- [ ] **FE-P3-44 · Tag badge foreground colors can fail contrast.**
  - **Problem:** User-defined tag colors use white text by default, which can fall below the 4.5:1 contrast ratio against lighter tag backgrounds.
  - **Source:** `resources/js/Components/FileItem.vue`.
  - **Fix:** Compute an accessible foreground color from the tag background, or fall back to a theme-safe badge style when no color is set.

- [ ] **FE-P3-45 · PDF preview iframes lack titles.**
  - **Problem:** Screen readers cannot identify the purpose of PDF preview iframes because they have no `title` attribute.
  - **Source:** `resources/js/Components/QuickLookModal.vue`, `resources/js/Components/SharedListing.vue`.
  - **Fix:** Add a `title` attribute describing the file being previewed.

- [ ] **FE-P3-46 · Hardcoded design tokens and magic numbers across components.**
  - **Problem:** Inline pixel values, fixed heights, viewport arithmetic, and ad-hoc z-index values bypass the design system and make responsive tweaks error-prone.
  - **Source:** `resources/js/Pages/Photos/Index.vue`, `resources/js/Layouts/AppLayout.vue`, `resources/js/Components/FileItem.vue`.
  - **Fix:** Move sizing and color values to CSS variables, adopt utility classes, and define a shared z-index scale.

- [ ] **FE-P3-47 · Bookmarks detail uses a raw Bootstrap button instead of VibeButton.**
  - **Problem:** The "Open" action in the bookmark detail pane is a plain `<a class="btn btn-primary">`, inconsistent with the rest of the app and missing VibeButton behaviors.
  - **Source:** `resources/js/Pages/Bookmarks/Index.vue`.
  - **Fix:** Replace the raw anchor with `<VibeButton variant="primary" external>` or the equivalent design-system component.

- [ ] **FE-P3-48 · Audit log uses inline conditional style strings.**
  - **Problem:** The audit log binds styles via a ternary string, making the rules hard to maintain and not responsive.
  - **Source:** `resources/js/Pages/Admin/Audit/Index.vue`.
  - **Fix:** Bind CSS classes instead and move the expanded/collapsed rules to scoped CSS.

- [ ] **FE-P3-49 · defineEmits declarations are untyped in heavy editors.**
  - **Problem:** `SpreadsheetEditor` and `DocxEditor` declare emits as string arrays, so event payloads are not type-checked.
  - **Source:** `resources/js/Components/SpreadsheetEditor.vue`, `resources/js/Components/DocxEditor.vue`.
  - **Fix:** Convert emit declarations to typed tuple form with explicit payload types.

### FE-P4 — Enhancements

- [ ] **FE-P4-01 · Vault reveal should fade/count down before masking.**
  - **Problem:** Secrets snap back to masked instantly after the timer fires, which is jarring.
  - **Source:** `resources/js/Pages/Vault/Index.vue:45-50`.
  - **Fix:** Add a CSS transition or a visible countdown before re-masking.
  - **Status:** ❄ **Frozen — re-evaluate v2.0.** Not in v1.0 scope; kept in backlog to preserve context, **not deleted**. Will be reconsidered after the v1.0 launch gate clears.

- [ ] **FE-P4-02 · Vault row hover elevation is missing.**
  - **Problem:** Rows do not visually lift on hover, making scan-ability poor.
  - **Source:** `resources/js/Pages/Vault/Index.vue:445`.
  - **Fix:** Add `hover:shadow-md` / `hover:bg-muted` tokens.
  - **Status:** ❄ **Frozen — re-evaluate v2.0.** Pure cosmetic, not a v1.0 ship-blocker. Kept to preserve the original observation.

- [ ] **FE-P4-03 · Star/un-star notes and bookmarks.**
  - **Problem:** Users cannot mark notes or bookmarks as favourites for quick access.
  - **Source:** `resources/js/Pages/Notes/Index.vue`, `Bookmarks/Index.vue`.
  - **Fix:** Add `starred` boolean column + toggle endpoint + starred filter/sidebar section.

- [ ] **FE-P4-04 · Starred page should aggregate starred items from all areas.**
  - **Problem:** There is no single view for starred files, notes, and bookmarks.
  - **Source:** New page required.
  - **Fix:** Create `/starred` route that queries across entities and groups by type.
  - **Status:** ❄ **Frozen — re-evaluate v2.0.** Depends on FE-P4-03 (star primitives) shipping first. Not in v1.0 scope.

- [ ] **FE-P4-05 · Recent activity across all areas.**
  - **Problem:** Users jump between Files, Photos, Notes, Bookmarks to see recent changes.
  - **Source:** Dashboard currently only shows files.
  - **Fix:** Add a unified recent-activity widget on `/dashboard`.
  - **Status:** ❄ **Frozen — re-evaluate v2.0.** Depends on FE-P4-17 (Smart Folders cross-surface). Not in v1.0 scope.

- [ ] **FE-P4-06 · Consistent sorting across listing pages.**
  - **Problem:** Each page re-implements sort UI with different defaults.
  - **Source:** `Files/Index`, `Photos/Index`, `Bookmarks/Index`, `Notes/Index`.
  - **Fix:** Extract a `SortDropdown` composable/adapter and align with `useUrlSync`.

- [ ] **FE-P4-07 · Vault per-user sharing.**
  - **Problem:** Secrets are user-private only; teams cannot share credentials.
  - **Source:** `resources/js/Pages/Vault/Index.vue`.
  - **Fix:** Add share UI reusing `ShareModal` and a `vault_shares` pivot table.
  - **Status:** ❄ **Frozen — re-evaluate v2.0.** Requires new schema (`vault_shares` pivot) + Shares UX rebuild. Not in v1.0 scope.

- [ ] **FE-P4-08 · Search by `accessed_at`.**
  - **Problem:** Recent files search does not surface most-recently-opened items.
  - **Source:** Search controllers.
  - **Fix:** Index and expose `accessed_at` in search queries and UI.
  - **Status:** ❄ **Frozen — re-evaluate v2.0.** Requires schema migration + DB index work. Recent view already exists. Not in v1.0 scope.

- [ ] **FE-P4-09 · Drag bookmarks to folders.**
  - **Problem:** Bookmark organisation requires opening edit modal.
  - **Source:** `resources/js/Pages/Bookmarks/Index.vue`.
  - **Fix:** Enable drag-and-drop reorder/move with HTML5 drag API.

- [ ] **FE-P4-10 · Make `ThreePane` resizable (sidebar + list widths stored per-user).**
  - **Problem:** `ThreePane.vue:5-8` hard-codes `sidebarWidth="230px"` and `listWidth="340px"`. A power user on a 4K monitor wastes pixels; on a 13" laptop the list truncates filenames. Apple Finder lets the user drag column dividers.
  - **Source:** `resources/js/Components/ThreePane.vue:5-23`.
  - **Fix:** Wrap each column in a draggable divider:
    ```vue
    <script setup>
    const props = defineProps({ initialSidebar: { type: Number, default: 230 }, initialList: { type: Number, default: 340 } });
    const sidebarWidth = ref(props.initialSidebar);
    const listWidth = ref(props.initialList);
    function startDrag(which: 'sidebar' | 'list', e: MouseEvent) {
        const startX = e.clientX;
        const startW = which === 'sidebar' ? sidebarWidth.value : listWidth.value;
        function onMove(ev: MouseEvent) {
            const next = Math.max(160, Math.min(560, startW + (ev.clientX - startX)));
            if (which === 'sidebar') sidebarWidth.value = next; else listWidth.value = next;
        }
        function onUp() { window.removeEventListener('mousemove', onMove); window.removeEventListener('mouseup', onUp); }
        window.addEventListener('mousemove', onMove);
        window.addEventListener('mouseup', onUp);
    }
    </script>
    <template>
        <div class="three-pane d-flex border rounded overflow-hidden bg-body">
            <div class="tp-sidebar" :style="{ width: sidebarWidth + 'px' }"><slot name="sidebar" /></div>
            <div class="tp-divider" @mousedown="startDrag('sidebar', $event)"></div>
            <div class="tp-list" :style="{ width: listWidth + 'px' }"><slot name="list" /></div>
            <div class="tp-divider" @mousedown="startDrag('list', $event)"></div>
            <div class="tp-detail flex-grow-1" style="min-width: 0"><slot name="detail" /></div>
        </div>
    </template>
    <style>
    .tp-divider { width: 4px; cursor: col-resize; background: transparent; }
    .tp-divider:hover { background: var(--bs-primary); }
    </style>
    ```
    Persist widths in `localStorage` (use the SSR-safe pattern from FE-P2-45).

- [ ] **FE-P4-11 · Drag-to-select a range (rectangle marquee) in Files grid.**
  - **Problem:** `useSelection.ts:13-67` supports Cmd-click, Shift-click range, and select-mode click — but no drag-rectangle. Power users select 50 files by sweeping the cursor.
  - **Source:** `resources/js/composables/useSelection.ts`, `Files/Index.vue` (grid view).
  - **Fix:** Add a `useMarqueeSelection` composable that paints a translucent `<div>` while the mouse drags with no modifier, then on `mouseup` calls `selectedIds.add` for every item whose bounding rect intersects the rect:
    ```ts
    // resources/js/composables/useMarqueeSelection.ts
    export function useMarqueeSelection(container: Ref<HTMLElement | null>, onSelect: (ids: number[]) => void) { /* … */ }
    ```
    Bind to the grid view's `@mousedown` (only when no item was clicked) and `@mousemove` / `@mouseup` on `window`.
  - **Status:** ❄ **Frozen — re-evaluate v2.0.** Power-user feature; tiny audience. Standard `useSelection` covers 97% of users. Not in v1.0 scope.

- [ ] **FE-P4-12 · Tabs (Finder-style) for open detail views.**
  - **Problem:** Opening a file in Quick Look and a note side-by-side means two browser tabs. Finder lets you tab within one window. Power users with 27" monitors want to keep 4–6 items in view.
  - **Source:** New feature.
  - **Fix:** Add a `useTabs()` composable + a `<TabBar>` mounted in `AppLayout`:
    ```ts
    // resources/js/composables/useTabs.ts
    interface Tab { id: number; kind: 'file' | 'note' | 'bookmark'; title: string; href: string }
    const state = reactive<{ tabs: Tab[]; activeId: number | null }>({ tabs: [], activeId: null });
    function open(tab: Tab) { if (!state.tabs.find((t) => t.id === tab.id)) state.tabs.push(tab); state.activeId = tab.id; }
    function close(id: number) { state.tabs = state.tabs.filter((t) => t.id !== id); if (state.activeId === id) state.activeId = state.tabs[0]?.id ?? null; }
    ```
    Tab clicks navigate via `router.visit`. Persist in `localStorage` so refresh restores the set.
  - **Status:** ❄ **Frozen — re-evaluate v2.0.** Browser tabs already cover this for 99% of users. Power-user feature, tiny audience. Not in v1.0 scope.

- [ ] **FE-P4-13 · Inline rename (F2 or double-click on the filename).**
  - **Problem:** Every rename today opens a modal (`RenameModal`, `Bookmarks/Index.vue:90-98`, `Vault/Index.vue:72-81`, `Notes/Index.vue:125-136`). Apple Finder lets you click the name once (after selection) and type the new name in place.
  - **Source:** All modals listed above.
  - **Fix:** Build an `InlineEditable` primitive:
    ```vue
    <!-- resources/js/Components/InlineEditable.vue -->
    <script setup lang="ts">
    const props = defineProps<{ modelValue: string; editable?: boolean }>();
    const emit = defineEmits<{ 'update:modelValue': [string]; save: [string] }>();
    const editing = ref(false);
    const draft = ref('');
    function start() { if (!props.editable) return; draft.value = props.modelValue; editing.value = true; }
    function commit() { if (draft.value && draft.value !== props.modelValue) { emit('update:modelValue', draft.value); emit('save', draft.value); } editing.value = false; }
    </script>
    <template>
        <input v-if="editing" ref="input" v-model="draft" @blur="commit" @keyup.enter="commit" @keyup.escape="editing = false" class="form-control form-control-sm" />
        <span v-else :class="{ 'text-truncate': true, 'editable': editable }" @dblclick="start">{{ modelValue }}</span>
    </template>
    ```
    Add an `F2` keydown handler in `useSelection` (or a separate `useInlineRename`) to enter edit mode on the active row.

- [ ] **FE-P4-14 · Bulk-tag editor (apply tags to N items at once).**
  - **Problem:** `Files/Index.vue:495-540` `Tags` modal is single-item. The `BatchActions` bar (`BatchActions.vue`) supports Move / New Folder / Rename / Delete — no "Add tag to N".
  - **Source:** `Files/Index.vue:495-540`, `BatchActions.vue:23-35`.
  - **Fix:** Add a `bulkTagOpen` flow in `BatchActions.vue` that opens a small modal with the existing tag-autocomplete; on confirm, `router.put('/files/bulk/tags', { ids, add: ['foo'], remove: ['bar'] })`. Backend: add the route + `File::whereIn('id', $ids)->each(fn ($f) => $f->syncTags([…]))`.

- [ ] **FE-P4-15 · Home dashboard route — recent activity + pinned + storage + smart folders.**
  - **Problem:** `/` is the file browser. There's no "what's new" surface. A user opening the app must click into Recent / Starred / Smart Folders to see activity.
  - **Source:** `routes/web.php` (no `/dashboard` route), `AppLayout.vue:108-118` (no dashboard link).
  - **Fix:** Add `/dashboard`:
    ```php
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    ```
    ```ts
    // resources/js/Pages/Dashboard/Index.vue
    // <script setup> fetches:
//   - recent: 5 most recently opened/edited files
//   - pinned: starred files, notes, bookmarks
//   - storage: 30-day usage sparkline
//   - activity: audit log feed
//   - smart folders: saved searches with quick "Run" buttons
    ```
    Redirect `/` → `/dashboard` for first-time visitors, leave the file browser at `/files`.
  - **Status:** ❄ **Frozen — re-evaluate v2.0.** Multi-page effort that depends on FE-P4-17. Not in v1.0 scope.

- [ ] **FE-P4-16 · Drag any file from a list → drop in the sidebar tree (Notes/Bookmarks) to move.**
  - **Problem:** `Files/Index.vue:161-164` has drop-onto-folder in the grid. `Notes/Index.vue` and `Bookmarks/Index.vue` have no drop targets; the only way to move a note into a folder is to open the note, scroll to the rename field, and re-pick a parent.
  - **Source:** `NotesSidebar.vue` (no drop handler), `Bookmarks/Index.vue` sidebar (no drop handler).
  - **Fix:** Use the existing `VibeDroppable` primitive on each sidebar row:
    ```vue
    <button v-for="folder in visibleRows" :key="folder.id" class="side-row …" @click="…">
        <VibeDroppable group="notes" @drop="(payload) => moveNote(payload, folder.id)">
            <template #default="{ isOver }">
                <div :class="{ 'bg-primary-subtle': isOver }">…</div>
            </template>
        </VibeDroppable>
    </button>
    ```
    `moveNote` calls `router.put(\`/notes/${payload.id}/move\`, { parent_id: folder.id })`.

- [ ] **FE-P4-17 · Saved views / Smart Folders as a first-class concept for Notes + Bookmarks + Vault.**
  - **Problem:** `useAdvancedSearch.ts:8-32` defines `AdvFilters` and the smart-folder save flow exists at `AdvancedSearchModal.vue:32-39`. Only Files uses it. Notes/Bookmarks/Vault each reinvent their own filter UX.
  - **Source:** `useAdvancedSearch.ts`, `AdvancedSearchModal.vue`, `AppLayout.vue:243-250` (smart-folder sidebar section).
  - **Fix:** Generalise the smart-folder shape to `{ scope: 'files' | 'notes' | 'bookmarks' | 'vault' | 'all', params: Record<string, unknown> }`. Persist the scope in `saved_searches` (DB migration). Render saved-folder chips on every relevant page, scoped to that page's `scope`.     Add `scope: 'notes'` to the Note filter sidebar, etc.
  - **Status:** ❄ **Frozen — re-evaluate v2.0.** Requires DB migration + per-surface work. Not in v1.0 scope.

- [ ] **FE-P4-18 · Quick Look from Bookmarks + Shared + Photos (single source of preview).**
  - **Problem:** Quick Look is reachable from Files only. A bookmark or shared-file URL has no preview; the user must download or open in a new tab. Photos uses its own lightbox (see FE-P2-44).
  - **Source:** `Bookmarks/Index.vue` (no QL), `SharedListing.vue` (hand-rolled modal, FE-P2-43), `Photos/Index.vue` (own lightbox, FE-P2-44).
  - **Fix:** Once `QuickLookModal` is the only preview (FE-P0-25), wire it everywhere: Bookmarks row click → `quickLook(bookmark)` (URLs only show a metadata card with the favicon, the title, the source feed); SharedListing row click → `quickLook(file)`; Photos (FE-P2-44).

### FE-P5 — Icebox

- [ ] **FE-P5-01 · Directory org-chart view.**
  - **Problem:** Directory only shows flat/search list; reporting structure is not visual.
  - **Source:** `resources/js/Pages/Directory/Index.vue`.
  - **Fix:** Add a tree/org-chart toggle using recursive component.
  - **Status:** ❄ **Frozen — re-evaluate v2.0.** Moonshot, 2-3 weeks of dev work. No v1.0 user demand.

- [ ] **FE-P5-02 · Bookmark drag-to-reorder folders.**
  - **Problem:** Folder order is static.
  - **Source:** `resources/js/Pages/Bookmarks/Index.vue`.
  - **Fix:** Add `position` column and drag sorting.
  - **Status:** ❄ **Frozen — re-evaluate v2.0.** Out of v1.0 scope. Migration + drag handler.

- [ ] **FE-P5-03 · RSS/Atom feed reader view.**
  - **Problem:** Feed content only shows as bookmarks; no reader UI.
  - **Source:** `resources/js/Pages/Bookmarks/Index.vue`.
  - **Fix:** Add an article-reader pane for feed items.
  - **Status:** ❄ **Frozen — re-evaluate v2.0.** Out of v1.0 scope. Bookmarks already store feed URLs; reader is a separate UX.

- [ ] **FE-P5-04 · Documents category sidebar.**
  - **Problem:** Documents area lacks category navigation.
  - **Source:** `resources/js/Pages/Documents/Index.vue`.
  - **Fix:** Add category tree sidebar with route-synced selection.
  - **Status:** ❄ **Frozen — re-evaluate v2.0.** `Documents/Index.vue` not in v1.0 scope.

- [ ] **FE-P5-05 · Per-surface theming (light / dark / sepia / high-contrast).**
  - **Problem:** `useColorMode` (from VibeUI) supports light / dark / auto. The grid + table chrome doesn't respond to `sepia` or `high-contrast` (a11y preference).
  - **Source:** `resources/js/app.js:12`, `useColorMode` from `@velkymx/vibeui`, `theme.css` (no sepia tokens).
  - **Fix:** Extend the theme tokens to sepia + high-contrast in `theme.css`; add a theme picker in `AppLayout` next to the existing `toggleColorMode` button.
  - **Status:** ❄ **Frozen — re-evaluate v2.0.** Niche. Light/dark/auto is sufficient for v1.0.

- [ ] **FE-P5-06 · App-wide activity stream + "undo" panel.**
  - **Problem:** Audit log exists (`Admin/Audit/Index.vue`) but is admin-only and is per-action filter, not a stream. Power users want a per-user "what changed in the last hour" timeline.
  - **Source:** `app/Http/Controllers/AuditController.php`, `app/Models/AuditLog.php`.
  - **Fix:** Add a `/activity` route that returns the requesting user's audit events, plus a `useActivityStream()` composable that polls every 30s and surfaces the new entries in a slide-in panel.
  - **Status:** ❄ **Frozen — re-evaluate v2.0.** Functionality is fully covered by the FE-P0-26 toast + undo system. Not in v1.0 scope.

- [ ] **FE-P5-07 · Apple-style back/forward history bar (above the page header).**
  - **Problem:** Inertia has built-in back/forward via `router.get`, but there's no UI. macOS Finder shows a back/forward chevron next to the path bar.
  - **Source:** New feature.
  - **Fix:** Add `useRouterHistory()` that wraps `router.on('navigate', …)` and exposes `{ canBack, canForward, back, forward }`. Mount two `VibeButton variant="light" size="sm"` chevrons in the AppLayout top bar (or the Files page header).
  - **Status:** ❄ **Frozen — re-evaluate v2.0.** Browser back/forward buttons already cover this. Cosmetic.

- [ ] **FE-P5-08 · Spotlight-style search surface (file content + OCR + image text).**
  - **Problem:** `useAdvancedSearch.ts` covers name/type/date/size. A power user wants to find "the file that mentioned 'Q3 budget'." Requires Tika / Tesseract / pgroonga for content indexing.
  - **Source:** `app/Services/FileSearch.php`, `app/Http/Controllers/SearchController.php`.
  - **Fix:** Add a `file_contents` table populated by a background job (`ProcessFileContent`); expose a `contains:` operator in `useAdvancedSearch`; index OCR text from `ProcessPhoto` into the same table.
  - **Status:** ❄ **Frozen — re-evaluate v2.0.** Multi-week infra project (Tika / Tesseract / pgroonga). Not in v1.0 scope.

### SEC — Security

- [ ] **SEC-P0-01 · `xlsx` package is vulnerable to prototype pollution / ReDoS (npm audit High).**
  - **Problem:** `xlsx@0.18.5` has unpatched CVEs and no upgrade path in the 0.18 line.
  - **Source:** `package-lock.json`.
  - **Fix:** Migrate to `xlsx` 0.20+ (SheetJS CE) or replace with `exceljs` / `papaparse` depending on usage.

- [ ] **SEC-P1-01 · `dompurify` XSS bypasses (npm audit Moderate).**
  - **Problem:** Current `dompurify` version has known bypass vectors.
  - **Source:** `package-lock.json`.
  - **Fix:** Upgrade to latest patched `dompurify` and re-run audit.

- [ ] **SEC-P1-02 · `quill` editor sanitisation (npm audit Moderate).**
  - **Problem:** `quill` is flagged; bundled editor HTML export may allow script injection if not sanitised before display.
  - **Source:** `package-lock.json`, `resources/js/components/EditorModal.vue`.
  - **Fix:** Keep quill (required by VibeUI) but always sanitise rendered HTML with DOMPurify before preview/save; upgrade quill if a patch exists.

- [ ] **SEC-P2-01 · Preview iframe / link URLs are not allowlisted.**
  - **Problem:** Arbitrary `download_url` / `preview_url` values can inject `javascript:` or `data:` schemes.
  - **Source:** `resources/js/components/QuickLook.vue`, `FilePreview.vue`.
  - **Fix:** Centralise `isAllowedUrl(url)` helper and block non-http/https/blob origins.

- [ ] **SEC-P0-02 · `glob@7.2.3` and `glob@10.5.0` are vulnerable — `npm run build` warning.**
  - **Problem:** `npm warn deprecated glob@7.2.3: Old versions of glob are not supported, and contain widely publicized security vulnerabilities, which have been fixed in the current version.` Same warning for `glob@10.5.0`. `glob` is a transitive dep of Vite / vitest / bootstrap tooling; the warnings fire on every `npm install` and `npm run build`.
  - **Source:** `npm ci && npm run build` warning output; `package-lock.json`.
  - **Fix:** Pin a patched version via npm `overrides`:
    ```json
    {
        "overrides": {
            "glob": "^10.5.0",
            "inflight": "npm:lru-cache@^10.0.0"
        }
    }
    ```
    Re-run `npm install`, then `npm run build` — confirm no deprecation warnings. Run `npm audit` and address any remaining High-severity findings. Document accepted risk in `docs/security.md` if a transitive dep is unfixable.

- [ ] **SEC-P1-03 · `inflight@1.0.6` is deprecated and leaks memory — `npm run build` warning.**
  - **Problem:** `npm warn deprecated inflight@1.0.6: This module is not supported, and leaks memory. Do not use it. Check out lru-cache if you want a good and tested way to coalesce async requests by a key value.` `inflight` is a transitive dep of Vite. On a long-running dev server the leaked listeners accumulate.
  - **Source:** `npm ci` warning output; `package-lock.json`.
  - **Fix:** Override with `lru-cache` (see SEC-P0-02). Alternatively wait for Vite to drop the dependency and add to `docs/security.md` as accepted risk.

- [ ] **SEC-P2-02 · `lodash.isequal@4.5.0` is deprecated — `npm run build` warning.**
  - **Problem:** `npm warn deprecated lodash.isequal@4.5.0: This package is deprecated. Use require('node:util').isDeepStrictEqual instead.` Not a security issue, but a dependency that the project should not ship.
  - **Source:** `npm ci` warning output.
  - **Fix:** Override or replace:
    ```json
    { "overrides": { "lodash.isequal": "npm:node-util" } }
    ```
    If a transitive consumer truly needs it, document in `docs/security.md` and add a comment in `package.json` explaining the override.

---

## 🧪 QA — Quality Assurance (a11y, e2e, visual regression)

This lane is the **release gate** for v1.0. Every P0 a11y / a11y-adjacent item in `FE-P0-*`, `FE-P1-*`, `FE-P2-*` becomes automatically verified by the work in this lane. Items here are **infrastructure**, not features.

- [ ] **QA-P1-01 · `axe-core` integrated into Vitest + Playwright; CI fails the build on a11y violations.**
  - **Problem:** The Lighthouse a11y gate in the ✅ Ready-for-v1.0 checklist is a one-shot manual check. There is no automated a11y assertion in `npm run test:unit`, no `axe.run()` in any spec, no a11y step in Playwright. The 30+ a11y items in this backlog (FE-P0-28 → 35, FE-P1-54 → 56, etc.) will be merged in by 7 different commits over 5 weeks — without automated assertions, regressions will land undetected.
  - **Source:** `package.json` (no `vitest-axe`, no `@axe-core/playwright`), `vitest.config.ts`, `tests/js/` (no a11y spec), `tests/e2e/` (absent — see FE-P0-39).
  - **Fix:** Install the two test-side packages, add three guards, ship one CI step.
    ```bash
    # package.json devDependencies
    npm install -D vitest-axe @axe-core/playwright axe-core
    ```
    ```ts
    // tests/js/a11y/forms.spec.ts
    import { describe, it, expect } from 'vitest';
    import { mount } from '@vue/test-utils';
    import { axe } from 'vitest-axe';
    import Login from '@/Pages/Auth/Login.vue';
    describe('Login form a11y', () => {
        it('has no axe violations', async () => {
            const wrapper = mount(Login, { global: { stubs: { Link: true } } });
            const results = await axe(wrapper.element as Element);
            expect(results).toHaveNoViolations();
        });
    });
    ```
    ```ts
    // tests/e2e/a11y.spec.ts (extends FE-P0-39)
    import { test, expect } from '@playwright/test';
    import AxeBuilder from '@axe-core/playwright';
    test('home page has no a11y violations', async ({ page }) => {
        await page.goto('/');
        const results = await new AxeBuilder({ page }).withTags(['wcag2a', 'wcag2aa', 'wcag22aa']).analyze();
        expect(results.violations).toEqual([]);
    });
    ```
    ```yaml
    # .github/workflows/ci.yml (new step inside the existing frontend-ci job)
    - name: A11y (Vitest + Playwright)
      run: |
        npm run test:unit -- tests/js/a11y
        npx playwright test tests/e2e/a11y.spec.ts
    ```
    Scope the unit-side axe to a curated set of representative pages: Login, Register, Profile, Bookmarks add/edit, Vault add/edit, Notes rename, Admin/Users/Edit. Scope the Playwright side to a smoke pass: `/`, `/login`, `/files`, `/bookmarks`, `/notes`, `/vault`, `/admin/users`. Each page asserts `wcag2a`, `wcag2aa`, and `wcag22aa` rule sets.
  - **TDD atomic steps:**
    1. Red: install `vitest-axe`, add `tests/js/a11y/login.spec.ts` asserting no violations. It fails (the Login form has no `aria-describedby` wiring yet — see FE-P0-29).
    2. Red: install `@axe-core/playwright`, add `tests/e2e/a11y.spec.ts` asserting the login route has no violations. It fails.
    3. Land FE-P0-28 → FE-P0-34 and REF-P1-06; the spec should turn green.
    4. Flip both specs to `required` in CI (currently `testIgnore` until the a11y foundation is done); remove the ignore before v1.0.
    5. Document the rule set in `docs/a11y.md` so future contributors know which WCAG tags we enforce.

- [ ] **QA-P1-02 · `playwright.config.ts` + `tests/e2e/` directory committed with a passing baseline.**
  - **Problem:** FE-P0-39 creates the Playwright configuration. This item is the **operational** counterpart: keep the E2E suite green, fast, and deterministic. Today there is one legacy `playwright.config.js` (no TS, no projects, no web server config). Without a steady cadence of "every PR runs the four baseline specs", the suite rots.
  - **Source:** `playwright.config.js` (legacy), `tests/e2e/` (absent).
  - **Fix:** Promote the four specs from FE-P0-39 (`a11y.spec.ts`, `files-crud.spec.ts`, `bulk-select.spec.ts`, `preview.spec.ts`) to the **required** CI check. Add a fifth: `auth.spec.ts` (login → logout → expired session → CSRF). Each spec must finish in <30s locally and <90s on CI. Add a `data-testid` convention document (`docs/testing.md`) so every new component has a stable test hook.
  - **TDD atomic steps:**
    1. Red: copy the FE-P0-39 specs into `tests/e2e/`. Run `npx playwright test` locally — they all pass against the dev server.
    2. Red: open a PR that deletes one assertion. CI must fail.
    3. Wire the `frontend-e2e` job in `.github/workflows/ci.yml` (currently `required: true` for typecheck + Vitest; this new job is `required: true` for v1.0).
    4. Add `data-testid` to the four most-touched components: `FileItem`, `Bookmarks/Index`, `Vault/Index`, `Notes/Index`. Verify the suite still passes (rename only).
    5. Document in `docs/testing.md`.

- [ ] **QA-P2-01 · Visual regression baseline (Playwright trace screenshots) for the 6 most-touched routes.**
  - **Problem:** No screenshot diff. Refactors to `AppLayout`, `PageHeader`, color tokens, the `ThreePane` shell, and the new shim layer (`AppModal`, `AppFormGroup`) will silently change pixel output. No one notices until a designer files a bug.
  - **Source:** `tests/e2e/` (absent), no `playwright` screenshot infrastructure.
  - **Fix:** Add a Playwright `screenshot.spec.ts` that captures a deterministic set of viewports (1440×900 desktop, 768×1024 tablet, 375×812 phone) on each of: `/`, `/files`, `/notes`, `/bookmarks`, `/vault`, `/admin/users`. Use `await expect(page).toHaveScreenshot('home-desktop.png', { maxDiffPixels: 100 })`. Commit the baseline screenshots; CI fails when a PR exceeds the diff threshold. Excluded from the required CI gate until the visual system stabilises.
  - **TDD atomic steps:**
    1. Red: write the spec, run it locally. The first run creates the baseline screenshots; commit them.
    2. Red: change a CSS variable in `theme.css` (e.g. `--bs-primary` from `#4f46e5` to `#3b82f6`). Spec fails with `maxDiffPixels` exceeded.
    3. Revert the change. Spec passes.
    4. Document the workflow in `docs/visual-regression.md` ("how to refresh the baseline" + "how to add a new route to the snapshot set").

- [ ] **QA-P3-01 · Lighthouse CI integration for the 6 critical routes.**
  - **Problem:** The ✅ Ready-for-v1.0 Gate checks "Lighthouse accessibility score ≥ 90" but the check is manual. We can run Lighthouse in CI via `treosh/lighthouse-ci-action` or `@lhci/cli` and gate PRs on the score.
  - **Source:** `.github/workflows/ci.yml` (no Lighthouse step), `package.json` (no `@lhci/cli`).
  - **Fix:** Add a `frontend-lighthouse` CI job that builds the app, serves it, runs Lighthouse against the 6 critical routes, and fails the build if accessibility or best-practices score drops below 90. Mirror the local run via `npm run lhci`.
  - **TDD atomic steps:**
    1. Red: install `@lhci/cli`, write `lighthouserc.cjs` with the 6 routes and the 90/90/90 thresholds.
    2. Run `npx lhci autorun` locally. The auth route may already pass 90 — others (Notes, Vault) may not. Note the baselines.
    3. Land FE-P0 a11y work; re-run. Scores climb.
    4. Wire the CI job; flip to `required: true` only after the 90 threshold is consistently met.

---

## ✅ Ready-for-v1.0 Gate

- [ ] No P0 broken-functionality items remain.
- [ ] TypeScript typecheck passes (`npm run typecheck`).
- [ ] Unit tests pass (`npm run test:unit`).
- [ ] Feature tests pass (`php artisan test`).
- [ ] No unpatched High-severity dependencies remain (or documented risk accepted).
- [ ] Lighthouse accessibility score ≥ 90 on key pages.
