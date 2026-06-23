# Changelog

> Completed work moved out of `todo.md`. Untracked, like `todo.md`.

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
