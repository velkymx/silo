# Changelog

> Completed work moved out of `todo.md`. Untracked, like `todo.md`.

## 2026-06 — Fun (Break Room)

- **Sodoku** at `/break/soduku` — traditional 9×9 with three difficulty tiers (beginner / intermediate / advanced) and a deterministic daily puzzle. Same date + same difficulty → same seed → same puzzle for every user. Pure logic in 4 TDD specs (solver, validator, hints, seed) covering 33 tests. Theme-aware (works in light + dark mode), `prefers-reduced-motion` respected. Keyboard shortcuts: `1-9` to fill, `←↑↓→` to move, `P` pencil, `N` new, `H` hint, `U`/`⌘Z` undo, `Space` pause. Pencil marks, undo, erase, hint (Naked-Single detection), check (3-mistake limit), win/lose modals, copy-result-to-clipboard, localStorage save/resume. Sidebar entry under a new "Break Room" section. The PHP generator was caught by a new PHPUnit spec (`SodokuGeneratorTest`, 8 cases) producing a 9-cell solution on the first run — root cause: `fillFromCell` was advancing to the next cell before attempting placement, so the recursion always tried to fill a cell whose row was already complete. Fixed by placing into the input cell, recursing to `nextCell`, and starting at `(1, 0)` (row 0 is pre-populated). Locked in by the spec.
- "Break Room" sidebar section (with Crush + Sodoku entries; Crush was previously in the user menu — moved for discoverability).

## 2026-06-24 — Break Room unification + admin fix

- Unified tile/grid sizing across all Break Room games (`Crush`, `Daily Word Game`, `Sodoku`) around the larger DWG tile size using shared CSS custom properties (`--break-tile-size`, `--break-tile-gap`) on `BreakGameShell.vue`.
- Wrapped `Sodoku` in `BreakGameShell.vue` so all three games now share the same header, card shell, and message-area layout.
- Refreshed Sodoku tile styling to match Crush/DWG: 2px bordered cells, primary selection ring, primary focus outline, and subtle tinted backgrounds for related/same-value cells.
- Moved Sodoku controls (difficulty selector, action buttons, mobile pad) out of the card into the shared `#extra` slot so the card contains only the board and hint alert, matching the other games.
- Fixed **FE-P0-11**: `AdminController::update` now accepts a nullable `group_id` and persists `null`; added `tests/Feature/AdminUserUpdateTest.php` covering null group, valid group, and non-admin rejection.

## 2026-06 — UI / UX & Features

- Multi-select + Finder-style batch operations: **New Folder from Selection**, **Batch Rename** (find/replace, add text before/after, sequential numbering with live preview), batch **Move** and **Delete**.
- Always-on multi-select checkbox to the left of every list item.
- Redesigned upload modal: large drag-and-drop zone, per-file list with thumbnails/sizes + remove, centered primary Upload button, graceful "POST too large" handling.
- Drag-and-drop files/folders onto a folder (grid, list, and sidebar tree) to move them (VibeDraggable).
- Confirm before deleting a folder that has contents (shows item count).
- **Smart folders**: save a search to the sidebar; re-run or delete inline; shown on every route.
- **Advanced (Gmail-style) search**: text + date range + min/max size + file type + tag, with a Filters modal; plus a **scope toggle** (current folder + subfolders vs all folders).
- Global search unified in the top bar (present on every page incl. Photos); ⌘K focus app-wide.
- Consistent sidebar: shared FOLDERS tree + storage meter + smart folders across all routes; icon section headings with aligned left edges; even nav-icon spacing; filetree redesign with open/closed icons + active state.
- Standardized root naming to **Home** (nav, breadcrumb, pickers).
- Breadcrumbs: chevron dividers, clear links with hover underline + pointer cursor, house/folder icons.
- Storage meter bottom-aligned in the sidebar.
- "Show N entries" selector cleanup on the files table.
- **Photos**: per-photo actions menu + clickable star; searchbar; carousel lightbox with prev/next nav buttons + thumbnail filmstrip (kept on-screen); grid menu z-index fix.
- **Storage analyzer** at `/usage` (WinDirStat / Disk Inventory X style): full nested squarified treemap — every file a colored tile inside its folder's box — plus by-type bars and largest-files list.

## 2026-06 — Production Hardening Sprint

- **H1** Stored-XSS fix: `raw()`/public-raw/thumbnail/avatar serve `attachment` by default (image/audio/video/pdf inline allowlist; svg/html/xml never inline); `nosniff` + per-file CSP.
- **H2** Site-wide Content-Security-Policy + `X-Frame-Options`/`Referrer-Policy`/`Permissions-Policy` middleware.
- **H3** Rate limiting: public share unlock (brute force, logged), uploads, public downloads.
- **H4** Antivirus scanning on upload (ClamAV, config-gated, fail-closed; infected files quarantined + blocked).
- **H5** Spreadsheet save preserves number formats, dates, currency, merged cells, column widths, and formulas (in-place workbook patch instead of value rebuild).
- **H6** Tested backup **restore** (`backup:restore` CLI + admin UI) — backup → wipe → restore verified byte-for-byte; backups stream blobs to disk with a free-space pre-check.
- **H7** `files:reconcile` re-queues stalled uploads; queue worker supervision (Docker) + scheduler.
- **H8** All fonts/icons bundled locally (no Google Fonts CDN); air-gap safe; CSP tightened to self-only.

## Earlier foundations

- Docker: `git clone` → `docker compose up` working app (PHP 8.4 + nginx + queue + scheduler under supervisor, SQLite, auto-migrate + admin seed); optional in-place import of a mounted host folder + admin Re-scan.
- Admin-scheduled compressed backups (download/restore), audit log, antivirus.
- In-browser editing: Markdown (Toast UI), HTML, Word (SuperDoc `.docx`), spreadsheets (jspreadsheet-ce + SheetJS) with git-style version notes; create blank Markdown/Spreadsheet/Word docs.
- Photos area (timeline, albums, lightbox, basic edits, star/tag/share).

## 2026-06-24 — Backlog cleanup: completed FE/BE items moved from `todo.md`

Vue 3 code review completed and all previously finished backlog items archived below.

### Frontend completed (old review / ship backlog)

- FE-P0-01 Share modal "Cancel" doesn't cancel
- FE-P0-02 Download triggers full page reload
- FE-P0-03 File action menu click-through / dropdown z-index
- FE-P0-04 Drag-and-drop move not working in grid/list
- FE-P0-05 Star action lacks visual feedback / optimistic update
- FE-P0-06 Batch rename preview missing / rename silently fails
- FE-P0-07 Batch move does not update UI after success
- FE-P0-08 Delete confirmation doesn't prevent accidental deletion
- FE-P0-09 Upload modal "Upload" button doesn't reset state after error
- FE-P0-10 "New folder from selection" leaves stale selection state
- FE-P1-01 File list re-renders entire table on every selection change
- FE-P1-02 No loading state during move/copy/delete
- FE-P1-03 FolderTree lacks keyboard/screen-reader a11y
- FE-P1-04 TagsModal uses inline edit + no optimistic updates
- FE-P1-05 QuickLook doesn't pre-fetch next/prev media
- FE-P1-06 DetailsPanel fetches on every row hover
- FE-P1-07 RenameModal doesn't auto-focus / select filename
- FE-P1-08 Search input not debounced
- FE-P1-09 Sidebar folder tree state resets on navigation
- FE-P1-10 Drag overlay missing while dragging
- FE-P1-11 File actions not reachable via keyboard
- FE-P1-12 Submit buttons lack busy/spinner state
- FE-P1-13 Inertia visits use hard `window.location` for downloads
- FE-P1-14 Pagination resets when sorting
- FE-P1-15 No empty state for empty folders / search results
- FE-P2-01 `useSelection` mutates props directly
- FE-P2-02 File mutation logic scattered in page component
- FE-P2-03 Duplicate event handlers for row click vs checkbox
- FE-P2-04 No guard against concurrent duplicate requests
- FE-P2-05 `v-model` on large folder tree causes lag
- FE-P2-06 Per-row `VibeButton` created per render (performance)
- FE-P2-07 Sort state stored locally instead of URL-synced
- FE-P2-08 `useBatchRename` lacks validation before submit
- FE-P2-09 Modals don't return focus to trigger on close
- FE-P2-10 Heavy modals imported eagerly (Upload/Share/Editor)
- FE-P2-11 `computed` re-calculates entire item list on minor filter change
- FE-P2-12 `router.reload()` used inside success callbacks (redundant)
- FE-P2-13 `useAdvancedSearch` fields not reset when modal reopened
- FE-P2-14 `VibeDataTable` emits row events without payload normalization
- FE-P2-15 No `AbortController` for long-running fetches
- FE-P2-16 `key` prop uses array index in several `v-for`
- FE-P2-17 `watchEffect` used where `watch` with explicit sources is clearer
- FE-P2-18 Props typed as `Object` / `Array` without shape
- FE-P2-19 CSS transitions not scoped to component
- FE-P3-01 Inconsistent icon sizes in file list
- FE-P3-02 File name truncation missing `title` tooltip
- FE-P3-03 Breadcrumb long-name overflow
- FE-P3-04 Storage meter label not right-aligned
- FE-P3-05 Folder tree indentation inconsistent
- FE-P3-06 Modal header close button missing focus ring
- FE-P3-07 Checkbox hit area too small
- FE-P3-08 Toast/notification messages use generic text
- FE-P3-09 Loading skeleton not used on initial file list load
- FE-P3-10 "Select all" checkbox state unclear for partial selection
- FE-P3-11 Mobile: action bar overlaps content
- FE-P3-12 Drop zone hint text not shown on touch devices
- FE-P3-13 Empty folder icon does not match theme
- FE-P3-14 Sort indicator direction confusing
- FE-P3-15 Share modal link input not auto-selected
- FE-P4-01 Resumable uploads
- FE-P4-02 Column customisation in file list
- FE-P4-03 Keyboard shortcut cheatsheet
- FE-P4-04 Bulk tag / metadata edit
- FE-P4-05 Drag-select rubber band
- FE-P4-06 File list density toggle
- FE-P4-07 Inline rename in grid
- FE-P4-08 Persistent filters per user
- FE-P5-01 Virtual scrolling for 10k+ items
- FE-P5-02 Offline support / PWA
- FE-P5-03 Full-text search inside documents
- FE-P5-04 AI auto-tagging
- FE-P5-05 Real-time collaboration
- FE-P5-06 File versioning UI (git-style diff)

### Tooling / architecture completed

- TypeScript 6 + Vitest 4 + Vite 8 toolchain; component-test harness.
- Missing tests identified: VSCode file tree + image gallery/slider (E2E).
- `axios` removed entirely; replaced with `lib/http` (fetch).
- C1 private disk default
- C2 trashed-name re-upload handling
- C3 cross-owner upload invariant enforced
- C4 name-lock via cache
- H11 upload-modal blob-URL leak fixed
- Decomposition so far: `useSelection`, `useBatchRename`, `useJobPolling`, `useAdvancedSearch`, `lib/fileTypes`, `lib/http`, `FileItem`+`ItemActions`, `AdvancedSearchModal`, `UploadModal`, `ShareModal`, `EditorModal`, `RenameModal`.

### Backend completed (old review / ship backlog)

- BE-P0-01 Public share unlock endpoint returns 500 on missing file
- BE-P0-02 Delete folder doesn't cascade quotas / storage
- BE-P0-03 Move endpoint allows cross-owner transfer
- BE-P0-04 Upload doesn't verify `parent_id` belongs to current user
- BE-P0-05 Share token generation uses short/non-unique strings
- BE-P0-06 FileController `raw()` sets inline disposition for SVG/HTML
- BE-P0-07 Mass-delete doesn't wrap in DB transaction
- BE-P0-08 Backup download doesn't verify user role
- BE-P1-01 StorageUsed cast issues
- BE-P1-02 No `lockForUpdate` on concurrent moves
- BE-P1-03 File `updated_at` not touched on content update
- BE-P1-04 Search endpoint not paginated
- BE-P1-05 Duplicate name check is racy
- BE-P1-06 No rate limit on public share unlock
- BE-P1-07 Missing model-level `name` normalisation
- BE-P1-08 Folder tree query N+1
- BE-P1-09 Soft-deleted files appear in move/copy targets
- BE-P1-10 Activity log doesn't log failures
- BE-P1-11 Tags sync not idempotent
- BE-P1-12 No eager loading on FilesController index
- BE-P1-13 Version note missing validation max length
- BE-P1-14 Antivirus result not tied to file record
- BE-P1-15 Public download lacks `Content-Length`
- BE-P1-16 Backup restore doesn't stream large files
- BE-P1-17 Import job doesn't report row-level errors
- BE-P1-18 Smart folder SQL injection surface
- BE-P1-19 Group admin endpoint lacks authorization
- BE-P1-20 Storage analyzer recursion unbounded
- BE-P1-21 No index on `files.parent_id`
- BE-P1-22 Email share doesn't validate recipient domain
- BE-P1-23 Activity log retention unbounded
- BE-P1-24 File content update doesn't create version snapshot
- BE-P1-25 Reconcile command doesn't verify checksums
- BE-P1-26 Share link password hashes lack algorithm agility
- BE-P1-27 Upload policy doesn't cap uploaded dimensions
- BE-P1-28 Trash restore doesn't check unique name
- BE-P1-29 Version controller missing authz checks
- BE-P2-01 Repository pattern for File operations
- BE-P2-02 API Resource classes
- BE-P2-03 FormRequest classes for multi-step validation
- BE-P2-04 Policy classes for File/Folder/Share
- BE-P2-05 DTOs for upload/move/share payloads
- BE-P2-06 Event sourcing for activity log
- BE-P2-07 Action classes for virus scan / reconcile / import
- BE-P2-08 Horizon dashboard for queues
- BE-P2-09 Caching layer for folder tree
- BE-P2-10 `stored` event for derived files (thumbnails, previews)
- BE-P2-11 Move file/folder endpoints to dedicated controllers
- BE-P2-12 Add cursor pagination for large folders
- BE-P2-13 Separate API routes for SPA vs web
- BE-P2-14 Service layer for backups
- BE-P3-01 Move attribute casting to dedicated casts
- BE-P3-02 Consolidate path/name validation rules
- BE-P3-03 Extract disk resolution logic
- BE-P3-04 Normalise mime/type helpers
- BE-P3-05 Reduce duplicated query scopes
- BE-P4-01 Increase feature-test coverage for Files area
- BE-P4-02 Add Dusk/browser tests for critical paths
- BE-P5-01 Performance benchmarks
- BE-P5-02 Load testing suite

### Cross-app completed

- Bookmark validate + favicon download + screenshot.
- Bulk maintenance: dedup / re-check all / hydrate missing.
- RSS/Atom feed detection on hydrate.
