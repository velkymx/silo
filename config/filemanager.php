<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Storage Disk
    |--------------------------------------------------------------------------
    |
    | The filesystem disk that newly uploaded files and created folders are
    | stored on. Any disk configured in config/filesystems.php may be used
    | (e.g. "public", "local", "s3"). Each file records its own disk in the
    | database, so changing this only affects future uploads.
    |
    */

    // Private by default: blobs are served only through the policy-gated
    // /raw/{file} route, never directly from the web root. Only set this to a
    // public/web-served disk if you accept that every stored file becomes
    // reachable by URL without auth.
    'disk' => env('FILEMANAGER_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Maximum Upload Size
    |--------------------------------------------------------------------------
    |
    | Maximum size, in kilobytes, allowed per uploaded file. Leave unset to
    | fall back to PHP's own limit (the smaller of upload_max_filesize and
    | post_max_size). Resolved via App\Support\Uploads::maxKb().
    |
    */

    'max_upload_kb' => env('FILEMANAGER_MAX_UPLOAD_KB'),

    /*
    |--------------------------------------------------------------------------
    | Trash Retention
    |--------------------------------------------------------------------------
    |
    | Days a soft-deleted item stays in the trash before the scheduled
    | `trash:purge` command permanently removes it and its blobs.
    |
    */

    'trash_retention_days' => env('FILEMANAGER_TRASH_RETENTION_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Public registration
    |--------------------------------------------------------------------------
    |
    | When false (default), the /register routes 404 — accounts are created by
    | an admin. Set ALLOW_REGISTRATION=true to open self-service signup.
    |
    */

    'allow_registration' => (bool) env('ALLOW_REGISTRATION', false),

    /*
    |--------------------------------------------------------------------------
    | Per-User Storage Quota
    |--------------------------------------------------------------------------
    |
    | Maximum total bytes a single user may store (counting current files,
    | trashed-but-not-purged files, and saved versions). Set to 0 for unlimited.
    |
    */

    'user_quota_mb' => env('FILEMANAGER_USER_QUOTA_MB', 1024),

    /*
    |--------------------------------------------------------------------------
    | Antivirus Scanning
    |--------------------------------------------------------------------------
    |
    | When enabled, every uploaded file is scanned (during ProcessUploadedFile)
    | with the configured command. A clamd-backed scanner is expected:
    | `clamdscan` is fast (daemon) while `clamscan` is standalone. The scanned
    | file path is appended to the command. Exit code 1 = infected, 0 = clean,
    | anything else = scanner error. On infection the blob is removed and the
    | file is flagged; on error the file is marked failed (fail closed).
    |
    */

    'av' => [
        'enabled' => env('FILEMANAGER_AV_ENABLED', false),
        'command' => env('FILEMANAGER_AV_COMMAND', 'clamdscan --no-summary --fdpass'),
        'timeout' => (int) env('FILEMANAGER_AV_TIMEOUT', 120),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notes
    |--------------------------------------------------------------------------
    |
    | Notes are markdown files living under a per-user "Notes" folder, edited
    | on the /notes surface. To avoid one saved version per autosave tick, the
    | autosave path snapshots a new version at most once every this many minutes
    | of continuous editing (explicit "Save version" always snapshots).
    |
    */

    'notes' => [
        'root_folder' => env('FILEMANAGER_NOTES_FOLDER', 'Notes'),
        'snapshot_interval' => (int) env('FILEMANAGER_NOTES_SNAPSHOT_INTERVAL', 10),
    ],

];
