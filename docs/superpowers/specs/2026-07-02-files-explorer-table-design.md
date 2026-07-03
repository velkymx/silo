# Files (`/`) Explorer-Table Layout — Design Spec

**Date:** 2026-07-02
**Scope:** `/` route (`resources/js/Pages/Files/Index.vue`, `FourPane.vue`, `FileItem.vue`, `ShellLayout.vue`)
**Decisions made with:** Alan, brainstorming session 2026-07-02

## Goal

Rebuild the contents/detail region of the file manager as a desktop-first,
Explorer-style table. Fix the filename-invisible list bug by replacing the
hand-rolled list wholesale. Audience: solo/family, desktop-first; mobile
keeps the existing pane-per-screen flow.

## Non-Goals (deferred to later rounds)

- Keyboard navigation (arrows/Enter/Space/F2/Del)
- Resizable panes (drag handles, persisted widths)
- Drop-anywhere OS file upload
- Miller/column view

## 1. Pane Structure — VibeRow/VibeCol grid

`FourPane.vue` keeps its public API (slots `rail`, `folders`, `topBar`,
`contents`, `detail`; `activePane` model) but its internals are rebuilt on
the VibeUI grid:

```
VibeRow (h-100 g-0 flex-nowrap, overflow-hidden)
├─ VibeCol cols="auto"        → rail (content-sized icon strip, ~56px)
├─ VibeCol :md="3" :lg="2"    → folders accordion
└─ VibeCol (equal-width fill) → right region, flex column:
     breadcrumb topBar (spans table + preview)
     VibeRow (g-0 flex-nowrap flex-grow-1)
     ├─ VibeCol (fill)                          → contents (table or grid)
     └─ VibeCol :lg="4" :xl="3" v-if="selected" → preview pane
```

- Widths are grid units, not hardcoded px. Rail stays `cols="auto"`
  (icon strip is content-sized; 1/12 would be too wide).
- Preview pane show/hide is `v-if` on its VibeCol; the sibling
  equal-width col reflows automatically.
- Every scrolling col gets `h-100 overflow-auto`; rows use `flex-nowrap`.
- Mobile (<md): existing pane-per-screen behavior is preserved — the
  active-pane hidden classes move onto the VibeCols, active pane gets
  full width, back chevron nav unchanged.

## 2. Contents Table (list view)

Replace the hand-rolled `list-group` markup in `Files/Index.vue` with
**VibeDataTable** (VibeUI built-in; already provides sort + pagination):

- Columns: **Name / Modified / Size / Kind**, clickable sortable headers.
- Name cell (via `#cell(name)` template) keeps: type icon with color,
  thumbnail when present, status badges (Processing/Infected/Failed),
  version badge, tag pills. Drag payload (VibeDraggable) and folder
  drop target (VibeDroppable) wrap the Name cell content if wrapping
  the whole `<tr>` fights DataTable markup.
- Modified and Kind columns hidden at narrow widths.
- DataTable pagination replaces the windowed "Show more" button.
- The old sort dropdown is removed — headers replace it.
- Old list markup is deleted, not kept behind a flag (rip out and
  replace; the filename-invisible bug dies with it).

## 3. Selection Model (Drive-style)

- Click folder row → navigate into folder.
- Click file row → select it; preview pane opens.
- Checkbox column: visible on row hover and whenever any checkbox is
  checked → multi-select. Works for touch without a mode toggle.
- BatchActions bar appears when one or more checkboxes are checked
  (existing component, unchanged behavior).
- The "select mode" toolbar toggle is removed entirely.
- Esc, or click on empty table space, clears selection → preview pane
  collapses.

## 4. Toolbar

One row. Keeps: Upload, New dropdown, Advanced-search funnel,
list/grid view toggle, and (grid mode only) thumbnail size control.
Removes: select-mode button and the whole per-folder
search + sort row.

**Search consolidation:** the contents-pane "Search this folder" input
is deleted. The navbar "Search everything" (⌘K) with its existing
scope dropdown (all folders / this folder) is the single search entry
point. Filter chips continue to show active scope/filters.

## 5. Grid View Polish

- Grid fills the full contents region (no more fixed 2 columns in a
  360px pane).
- Responsive columns via CSS `auto-fill/minmax` sizing.
- S/M/L thumbnail size control in the toolbar, visible only in grid
  mode; choice adjusts the minmax basis.
- Same selection model as the table: click card = select (file) or
  navigate (folder); hover checkbox for multi-select.

## 6. Preview Pane

- Auto show/hide: slides in when a file is selected, collapses to zero
  when selection clears. No permanent "Select a file" placeholder
  state on desktop.
- Content unchanged: type-aware FilePreview, name, Type/Size/Modified
  info list, read-only flag, actions (Preview/Open, Edit, Share,
  Download).
- Trash variant (Restore / Delete forever) unchanged.

## 7. Known Bug Fixed by This Work

List view currently renders rows with the filename invisible (icon
overlaps the modified date; name span collapses — `FileItem.vue:128`
area). The table rebuild replaces that markup wholesale.

## Risks / Fallbacks

- **VibeDataTable flexibility:** needs custom cell templates and a row
  click that is distinct from checkbox clicks. `#cell()` templates are
  already proven in this codebase (versions modal). If per-row
  VibeDraggable around `<tr>` conflicts with table markup, the
  draggable wraps the Name-cell content instead.
- **Grid-unit folder pane** may feel too wide/narrow at some
  breakpoints; tune `md`/`lg` values during implementation.

## Testing

- Component behavior: sort by each column, folder click navigates,
  file click opens preview, checkbox multi-select shows BatchActions,
  Esc collapses preview, pagination pages.
- Regression: mobile pane-per-screen walk (rail → folders → contents →
  detail and back), tag filtering, trash restore/purge, upload and
  New menus still reachable from the single toolbar row.
- Visual: filenames visible in list rows (the original bug).
