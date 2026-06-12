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

    'disk' => env('FILEMANAGER_DISK', 'public'),

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
    | Per-User Storage Quota
    |--------------------------------------------------------------------------
    |
    | Maximum total bytes a single user may store (counting current files,
    | trashed-but-not-purged files, and saved versions). Set to 0 for unlimited.
    |
    */

    'user_quota_mb' => env('FILEMANAGER_USER_QUOTA_MB', 1024),

];
