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
    | Maximum size, in kilobytes, allowed per uploaded file.
    |
    */

    'max_upload_kb' => env('FILEMANAGER_MAX_UPLOAD_KB', 5120),

];
