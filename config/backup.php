<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Backup destination disk
    |--------------------------------------------------------------------------
    |
    | The filesystem disk (from config/filesystems.php) backup archives are
    | written to. Default 'local' keeps everything on this server, which is a
    | single point of failure: a backup on the same volume as your data is not
    | a backup. Point BACKUP_DISK at an offsite disk (e.g. an s3 target) so a
    | disk loss cannot take the data and its backups at once. The System Health
    | card warns while this stays on-server or shares the data disk.
    |
    */

    'disk' => env('BACKUP_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Freshness threshold
    |--------------------------------------------------------------------------
    |
    | A successful backup older than this many days is reported as stale on the
    | System Health card.
    |
    */

    'max_age_days' => (int) env('BACKUP_MAX_AGE_DAYS', 7),

];
