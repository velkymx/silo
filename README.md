# File Manager by AJBApps

A self-hosted file manager built with Laravel 13 and an Inertia + Vue 3 single-page UI on [VibeUI](https://www.npmjs.com/package/@velkymx/vibeui) (Bootstrap 5.3). Files and folders are modelled in the database, every action is authorized by policy, uploads are virus-scanned and processed asynchronously for metadata and thumbnails, and common document types can be **edited in the browser**.

---

## Features

- **Unified file browser** — a Finder/Dropbox-style list or thumbnail grid (folders first, then files) with name/modified/size columns, colored type icons and image thumbnails, breadcrumb navigation, a collapsible folder-tree sidebar, and list/grid toggle.
- **Light / dark / auto theme** — follows the OS in auto mode, one-click toggle, persisted.
- **Upload, download, copy, move, rename, delete** — transactional DB + disk operations; storage is flat per user with the hierarchy held in the database.
- **In-browser editing** — Markdown (WYSIWYG + preview), HTML, Word (`.docx`), and spreadsheets (`.xlsx/.xls/.csv/.ods`) — see [Editing files](#editing-files).
- **Quick Look** — inline preview for images, PDFs, audio, video, rendered Markdown, Office docs, and text (spacebar or click), with keyboard paging and a one-click **Actions** menu.
- **Versioning with change notes** — every save keeps the previous content as a version with an optional Git-style "what changed" note; download or restore any prior version.
- **Search** — full-text search (Laravel Scout) across every folder you own (file name, MIME type, extracted metadata).
- **Tags & favorites** — colored tags and starring, with one-click filtering.
- **Sharing** — per-file/per-folder access for users (by email) or groups, with permission **inheritance** down the tree, a "Shared with me" view, and **public share links** (optional expiry, optional password, view-only or download).
- **Trash & restore** — soft-deleted items go to Trash and can be restored; a scheduled job purges them after a retention window.
- **Antivirus scanning** — every upload is scanned (ClamAV) before it becomes available; infected files are quarantined and blocked.
- **Metadata & thumbnails** — background jobs refine the MIME type, extract EXIF / image dimensions / ID3 audio tags / text snippets, and generate image (and PDF first-page) thumbnails.
- **Quotas** — per-user storage limit with a usage indicator.
- **Admin** — manage users and groups, view a security audit log, and create/schedule **compressed backups**.
- **Hardened serving** — files are served as attachments unless safely previewable, with `nosniff` + a strict Content-Security-Policy; sensitive routes are rate-limited.

![File Manager Screenshot](screenshot.png)

---

## Supported file types

### Preview (Quick Look)

| Type | Extensions / MIME | How it renders |
|------|-------------------|----------------|
| Images | `jpg` `jpeg` `png` `gif` `webp` (`image/*`) | Inline image |
| PDF | `pdf` | Inline iframe |
| Audio | `audio/*` | `<audio>` player |
| Video | `video/*` | `<video>` player |
| Markdown | `md` `markdown` | **Rendered as HTML** (Toast UI viewer) |
| Word | `docx` | Rendered document (docx-preview) |
| Spreadsheet | `xlsx` `xls` `csv` `ods` | Rendered table (SheetJS) |
| Text | `txt` `log`, etc. | Extracted text snippet |
| Anything else | — | Icon + download prompt |

### Edit (in the browser)

| Editor | Extensions | Engine | Where it opens |
|--------|-----------|--------|----------------|
| Markdown | `md` `markdown` `txt` `text` `log` | Toast UI Editor (WYSIWYG + Markdown + Preview) | Full-screen modal |
| HTML | `html` `htm` | VibeUI WYSIWYG (Quill) | Full-screen modal |
| Word | `docx` | SuperDoc (true `.docx` round-trip) | Full-screen editor page |
| Spreadsheet | `xlsx` `xls` `csv` `ods` | jspreadsheet-ce + SheetJS | Full-screen editor page |

**Spreadsheet editor** includes a formula bar and the full jspreadsheet feature set: live formulas (`=SUM(A1:A5)`), a formatting toolbar, multiple sheets/tabs, search, header filters, column sort/drag/resize, row resize/drag, insert/delete rows & columns, merged cells, comments, and word wrap. Saving preserves number formats, dates, currency, merged cells, column widths, and formulas. (Rich cell styling — font/fill colors — is not written back by the bundled engine.)

> Uploads of any type are accepted (subject to the size cap and virus scan). The tables above describe what can be **previewed/edited**; everything else can still be stored, shared, versioned, and downloaded.

---

## Using the app

Everything below is done from the UI — no shell access required.

### Browsing
- Click a folder to open it; use the **breadcrumb** or the **sidebar tree** to navigate.
- Toggle **list / grid** view from the toolbar (remembered per browser).
- Toggle the sidebar with the menu button; switch **theme** with the sun/moon pill.

### Creating & uploading
- **New ▾** menu: **New Folder**, **Markdown file** (opens the editor on a blank doc), or **Upload files** (multi-file, drag-and-drop).

### Editing files
- Open a file's **⋯ menu → Edit** (or **Actions → Edit** inside Quick Look).
- Text/Markdown/HTML open in a full-screen modal; Office docs open on a full-screen editor page with a **Full screen** toggle.
- Click **Save** → a **"What changed?"** popup (optional note) records a new **version**.

### Previewing
- Click a file (or press **space**) for **Quick Look**; arrow buttons page through files; the **Actions** menu runs any file action without leaving the preview.

### Versions
- **⋯ menu → Versions** lists every prior save with its change note, size, and date — **download** or **restore** any version.

### Sharing
- **⋯ menu → Share**: grant **view/download/edit** to a user (by email) or a group; grants on a folder **inherit** to everything inside.
- Create a **public link** with optional **password**, **expiry**, and **download** toggle; copy or revoke it any time.

### Organizing
- **Tags**: add colored tags from the **⋯ menu → Tags**, then click a tag to filter.
- **Star** a file/folder to find it under Starred.
- **Rename / Move / Copy / Delete** from the **⋯ menu**. Deleted items go to **Trash** and can be **restored**.

### Profile
- **Profile**: change name/email/password and **upload + crop** an avatar.

### Admin (admins only)
- **Users** — assign groups, toggle admin.
- **Groups** — create/manage groups used in sharing.
- **Audit** — security-relevant action log.
- **Backups** — set a schedule (off/daily/weekly/monthly) + retention, **Back up now**, and **download** or delete any archive. See [Backups](#backups).

---

## Requirements

- PHP **8.3+** with `gd`, `exif`, and `fileinfo` (thumbnails + metadata). `imagick` adds **PDF thumbnails**; `bz2` enables **ultra** backup compression.
- Composer 2.x
- Node.js **20+** and npm
- MySQL 8 / MariaDB / PostgreSQL / SQLite
- A queue worker process (see [Background processing](#background-processing))
- _Optional:_ **ClamAV** (`clamd`) for upload virus scanning

---

## Setup

```bash
# 1. Dependencies
composer install
npm install

# 2. Environment
cp .env.example .env
php artisan key:generate
#   edit .env: database credentials, FILESYSTEM_DISK, and the FILEMANAGER_* options

# 3. Database
php artisan migrate

# 4. Public storage symlink (so the configured disk is reachable)
php artisan storage:link

# 5. Build the front end
npm run build      # or: npm run dev   (during development)

# 6. Serve
php artisan serve
```

The app is then available at http://127.0.0.1:8000.

### Create an admin

```bash
php artisan tinker
>>> \App\Models\User::where('email', 'you@example.com')->update(['is_admin' => true]);
```

---

## Background processing

Uploaded files are processed off the request cycle (virus scan, MIME refinement, metadata, thumbnails). **A queue worker must be running**, or files stay in "Processing":

```bash
php artisan queue:work
```

Run the worker under a supervisor in production, and run the scheduler (one cron line) so trash purging, stalled-upload reconciliation (`files:reconcile`), and scheduled backups fire:

```cron
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

If a worker dies mid-batch, `files:reconcile` (scheduled every 10 min) re-queues anything stuck in "Processing".

---

## Backups

Admins create on-demand or scheduled backups from **Admin → Backups**. Each backup is an **ultra-compressed** archive (bzip2 when `bz2` is available, otherwise deflate) containing a **database dump** + **all stored file blobs** + a manifest, stored on the private `local` disk and downloadable from the UI. Retention prunes the oldest archives beyond the configured count.

You can also run one from the CLI:

```bash
php artisan backup:run
```

---

## Configuration

Key options in `config/filemanager.php` (overridable via `.env`):

| Variable | Default | Purpose |
|----------|---------|---------|
| `FILEMANAGER_DISK` | `public` | Storage disk for uploads (any disk in `config/filesystems.php`). |
| `FILEMANAGER_MAX_UPLOAD_KB` | _(unset)_ | Max KB per file. Unset → PHP's `upload_max_filesize` / `post_max_size`. |
| `FILEMANAGER_USER_QUOTA_MB` | `1024` | Per-user storage quota in MB (`0` = unlimited). |
| `FILEMANAGER_TRASH_RETENTION_DAYS` | `30` | Days an item stays in trash before `trash:purge` removes it. |
| `FILEMANAGER_AV_ENABLED` | `false` | Scan uploads with the configured antivirus command. |
| `FILEMANAGER_AV_COMMAND` | `clamdscan --no-summary --fdpass` | Scanner command (the file path is appended). |
| `FILEMANAGER_AV_TIMEOUT` | `120` | Scan timeout in seconds. |
| `SCOUT_DRIVER` | `database` | Search engine (`database`, `meilisearch`, `typesense`, …). |

---

## Security

- File bodies are served `Content-Disposition: attachment` unless they are a safe, previewable type (image/audio/video/PDF); `svg`/`html`/`xml` are never inline. All responses send `X-Content-Type-Options: nosniff`.
- A site-wide **Content-Security-Policy** plus `X-Frame-Options`, `Referrer-Policy`, and `Permissions-Policy` are applied by middleware.
- Public share **unlock** is rate-limited and failed attempts are logged; uploads and public downloads are throttled.
- Uploads are virus-scanned (when enabled) and **fail closed** — infected or unscannable files are not served.

---

## Importing an existing server folder

Admins can index an existing directory (e.g. a folder under `public/` or a symlink) **in place** without copying:

```bash
php artisan files:import --disk=public --owner=1 --path=existing/folder --into= --name="Imported"
```

Imported files are marked *referenced* — purging trash never deletes the original source blobs.

---

## Testing

```bash
php artisan test       # backend feature + unit tests
npm run test:e2e       # Playwright browser end-to-end suite
```

The end-to-end suite boots the app, seeds a deterministic admin, and drives the real UI in a headless browser.

---

## Stack

Laravel 13 · Inertia.js · Vue 3 · VibeUI (Bootstrap 5.3) · Laravel Scout · Intervention Image · getID3 · Toast UI Editor · jspreadsheet-ce · SheetJS · SuperDoc.

---

## License

Released under the **MIT License** — see [LICENSE](LICENSE).
