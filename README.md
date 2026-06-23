<div align="center">

![File Manager Screenshot](screenshot.png)

[![CI](https://github.com/velkymx/laravel-file-manager/actions/workflows/ci.yml/badge.svg)](https://github.com/velkymx/laravel-file-manager/actions/workflows/ci.yml)
[![Docker](https://img.shields.io/badge/Docker-ready-2496ED?logo=docker&logoColor=white)](#quick-start-with-docker-recommended)
[![Laravel 13](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)](https://laravel.com/)
[![PHP 8.3+](https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![Vue 3](https://img.shields.io/badge/Vue-3-42b883?logo=vuedotjs&logoColor=white)](https://vuejs.org/)
[![Bootstrap 5.3](https://img.shields.io/badge/Bootstrap-5.3-7952B3?logo=bootstrap&logoColor=white)](https://getbootstrap.com/)
[![TypeScript](https://img.shields.io/badge/TypeScript-strict-blue?logo=typescript&logoColor=white)](https://www.typescriptlang.org/)
[![License](https://img.shields.io/badge/license-MIT-green.svg)](LICENSE)

</div>

# File Manager by AJBApps

**Your own private Dropbox — running on your server, owned by you.**

A free, **open-source, self-hosted file manager** for Laravel — a privacy-first **alternative to Dropbox, Google Drive, and Nextcloud** that you run on your own server.

Open it in any browser and you get the convenience of Dropbox or Google Drive without handing your files to anyone else. Drag in documents, photos, and spreadsheets; see them in a clean Finder-style grid; press **space** to preview almost anything; edit Word, Excel, and Markdown **right in the page**; then share a link or grant a teammate access. Every file lives in **your** database, on **your** storage — nothing leaves your server.

Under the hood it's **Laravel 13 + Vue 3**, built for real use: every action is permission-checked, every upload is processed in the background for thumbnails and metadata (and optionally virus-scanned), every save is versioned, and every sensitive action is audit-logged. Point it at MySQL/MariaDB/Postgres/SQLite and a storage disk (local or S3), run one queue worker, and you have a production file platform.

**For** teams, self-hosters, agencies, privacy-sensitive orgs, and homelabs who want Drive-grade UX on their own hardware.

---

## Features

_Ordered by how much you'll touch them day to day._

- **Browse & organize** — a Finder/Dropbox-style thumbnail grid or list (folders first), image thumbnails, colored type icons, breadcrumb navigation, and a list/grid toggle remembered per browser.
- **Quick Look** — press **space** (or click) to preview images, PDFs, audio, video, rendered Markdown, Office docs, and text; arrow-key paging and a one-click **Actions** menu, no download needed.
- **Edit in the browser** — Markdown (WYSIWYG + preview), HTML, Word (`.docx`), and spreadsheets (`.xlsx/.xls/.csv/.ods`) with formulas — see [Editing files](#editing-files).
- **Upload, download, move, copy, rename, delete** — drag-and-drop multi-file upload; transactional database + disk operations so the tree never desyncs.
- **Search that actually finds it** — match on file **name and content** (extracted text/metadata), then narrow by date (uploaded or edited), size, type, tag, or folder. Save any search as a **Smart Folder** in the sidebar.
- **Sharing** — grant view/download/edit to a user (by email) or a group; grants on a folder **inherit** to everything inside. Create **public links** with optional password, expiry, and download toggle. "Shared with me" view included.
- **Photos** — a timeline gallery grouped by month, **albums**, a full-screen lightbox with slideshow, drag-to-reorder, and in-browser **crop / rotate / flip** saved as a new version.
- **Versioning with change notes** — every save keeps the prior content as a version with an optional "what changed" note; download or restore any earlier version.
- **Tags & favorites** — colored tags and starring, each one-click to filter.
- **Trash & restore** — deleted items go to Trash and restore cleanly; a scheduled job purges them after a retention window.
- **Storage insight & quotas** — a per-type treemap of what's using space, largest-files list, and a per-user quota with a usage meter.
- **Light / dark / auto theme** — follows the OS in auto mode, one-click toggle, persisted.

### Trust, security & operations

- **Admin** — manage users and groups, read a security **audit log**, and create/schedule **compressed backups** (DB dump + blobs + manifest).
- **Antivirus scanning** _(optional)_ — when enabled, every upload is scanned (ClamAV) before it becomes available; infected or unscannable files are quarantined and blocked (fail-closed).
- **Metadata & thumbnails** — background jobs refine the MIME type, extract EXIF / image dimensions / ID3 audio tags / text snippets, and generate image (and PDF first-page) thumbnails.
- **Hardened serving** — files are served as attachments unless safely previewable, with `nosniff` and a strict Content-Security-Policy; public unlock and uploads are rate-limited.

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

## Quick start with Docker (recommended)

The fastest way to self-host. One image bundles nginx, PHP-FPM, and the queue worker; on first boot it migrates the database, creates your admin account, and starts serving — no manual steps.

```bash
git clone https://github.com/velkymx/laravel-file-manager.git
cd laravel-file-manager

# Set your admin login, then start.
ADMIN_EMAIL=you@example.com ADMIN_PASSWORD='change-me' docker compose up -d
```

Open **http://localhost:8080** and sign in with those credentials.

- **Data persists** in the `app_storage` volume (uploads + the SQLite database + the app key survive container recreation).
- **Change the port** with `APP_PORT` (e.g. `APP_PORT=9000 docker compose up -d`).
- **Index an existing folder in place** — set `IMPORT_ENABLED=true` and mount it at `/import` (uncomment the volume in `docker-compose.yml`); files are referenced, never copied or deleted.
- **Virus scanning** — uncomment the `clamav` service and set `FILEMANAGER_AV_ENABLED=true`.

By default it runs on SQLite for a zero-dependency start; point `DB_*` at MySQL/MariaDB/PostgreSQL for production scale. For a manual (non-Docker) install, see [Setup](#setup) below.

---

## Requirements

> Skip this section if you're using Docker above — the image ships every dependency.

- PHP **8.3+** with `gd`, `exif`, and `fileinfo` (thumbnails + metadata). `imagick` adds **PDF thumbnails**; `bz2` enables **ultra** backup compression.
- Composer 2.x
- Node.js **24+** and npm
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
| `FILEMANAGER_NOTES_FOLDER` | `Notes` | Name of the per-user root folder for the Notes surface. |
| `FILEMANAGER_NOTES_SNAPSHOT_INTERVAL` | `10` | Minutes of continuous editing before notes autosave archives a new version (explicit "Save version" always snapshots). |
| `VAULT_KEY` | _(falls back to `APP_KEY`)_ | Base64 32-byte key for encrypting Secrets Vault entries at rest (`php artisan vault:key`). Server-side encryption, not zero-knowledge; back it up. |
| `ALLOW_REGISTRATION` | `false` | Open self-service signup. When `false`, the `/register` routes return 404 and accounts are created by an admin. |
| `ADMIN_EMAIL` | `admin@example.com` | Email of the admin account created by `db:seed` on a clean build. |
| `ADMIN_PASSWORD` | `password` | Password for the seeded admin (change for any non-local deploy). |
| `ADMIN_NAME` | `Administrator` | Display name for the seeded admin. |
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
