# File Manager

A self-hosted file manager built with Laravel 13 and an Inertia + Vue 3 single-page UI on [VibeUI](https://www.npmjs.com/package/@velkymx/vibeui) (Bootstrap 5.3). Files and folders are modelled in the database, every action is authorized by policy, and uploads are processed asynchronously for metadata and thumbnails.

---

## Features

- **Unified file browser** — a single Finder/Dropbox-style list (folders first, then files) with name/modified/size columns, icons and image thumbnails, breadcrumb navigation, and a lazy folder tree sidebar.
- **Light / dark / auto theme** — follows the operating system in auto mode, with a one-click toggle that persists.
- **Upload, download, copy, move, rename, delete** — DB + disk operations in transactions; storage is flat per user with the hierarchy held entirely in the database.
- **Quick Look** — inline preview for images, PDFs, audio, video, and text (spacebar or click), with keyboard paging.
- **Search** — full-text search (Laravel Scout) across every folder you own, over file name, MIME type, and extracted metadata.
- **Tags** — colored tags on files and folders, with one-click tag filtering.
- **Sharing** — per-file and per-folder access control for users (by email) or groups, with permission **inheritance** down the folder tree, a "Shared with me" view, and **public share links** (optional expiry, optional password, view-only or download).
- **Versioning** — re-uploading a file keeps the previous content as a version; download or restore any prior version.
- **Trash & restore** — soft-deleted items go to a Trash view and can be restored; a scheduled job permanently purges them after a retention window.
- **Metadata & thumbnails** — background jobs refine the MIME type, extract EXIF / image dimensions / audio (ID3) tags / text snippets, and generate image thumbnails.
- **Quotas** — per-user storage limit with a usage indicator.
- **Admin** — manage users, groups, and view an audit log of security-relevant actions.

![File Manager Screenshot](screenshot.png)

---

## Requirements

- PHP **8.3+** with the `gd`, `exif`, and `fileinfo` extensions (for thumbnails and metadata)
- Composer 2.x
- Node.js **20+** and npm
- MySQL 8 / MariaDB
- A queue worker process (see [Background processing](#background-processing))

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

Uploaded files are processed off the request cycle (MIME refinement, metadata extraction, thumbnails). **A queue worker must be running**, or files stay in the "Processing" state:

```bash
php artisan queue:work
```

For production, run the worker under a supervisor (`deploy/supervisor-worker.conf`) and run the scheduler so the trash-retention purge executes (`deploy/crontab`):

```cron
* * * * * cd /path/to/app && php artisan schedule:run >> /dev/null 2>&1
```

Password reset and verification email require a real mailer — set `MAIL_MAILER=smtp` (and credentials) in `.env`, otherwise mail is written to the log.

---

## Configuration

Key options in `config/filemanager.php` (overridable via `.env`):

| Variable | Default | Purpose |
|----------|---------|---------|
| `FILEMANAGER_DISK` | `public` | Storage disk for uploads (any disk in `config/filesystems.php`). |
| `FILEMANAGER_MAX_UPLOAD_KB` | _(unset)_ | Max KB per file. When unset, falls back to PHP's `upload_max_filesize` / `post_max_size`. |
| `FILEMANAGER_USER_QUOTA_MB` | `1024` | Per-user storage quota in MB (`0` = unlimited). |
| `FILEMANAGER_TRASH_RETENTION_DAYS` | `30` | Days an item stays in the trash before `trash:purge` removes it. |
| `SCOUT_DRIVER` | `database` | Search engine (`database`, `meilisearch`, `typesense`, …). |

---

## Testing

```bash
php artisan test       # backend feature + unit tests
npm run test:e2e       # Playwright browser end-to-end suite
```

The end-to-end suite boots the app, seeds a deterministic admin, and drives the real UI in a headless browser.

---

## Stack

Laravel 13 · Inertia.js · Vue 3 · VibeUI (Bootstrap 5.3) · Laravel Scout · Intervention Image · getID3.

---

## License

Licensed under the **Creative Commons Attribution-NonCommercial-ShareAlike 4.0 International (CC BY-NC-SA 4.0)**.
