<?php

namespace App\Support;

class Uploads
{
    /**
     * Effective max upload size per file, in kilobytes.
     *
     * Uses FILEMANAGER_MAX_UPLOAD_KB when set; otherwise falls back to PHP's
     * own limit (the smaller of upload_max_filesize and post_max_size).
     */
    public static function maxKb(): int
    {
        $configured = config('filemanager.max_upload_kb');
        if ($configured) {
            return (int) $configured;
        }

        $upload = self::toBytes((string) ini_get('upload_max_filesize'));
        $post = self::toBytes((string) ini_get('post_max_size'));

        // post_max_size = 0 means unlimited; ignore it in that case.
        $limits = array_filter([$upload, $post > 0 ? $post : null]);
        $bytes = $limits ? min($limits) : $upload;

        return max(1, intdiv($bytes, 1024));
    }

    /**
     * Parse a PHP ini shorthand size ("2M", "512K", "1G") into bytes.
     */
    protected static function toBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $number = (int) $value;
        switch (strtolower($value[strlen($value) - 1])) {
            case 'g':
                $number *= 1024;
                // no break
            case 'm':
                $number *= 1024;
                // no break
            case 'k':
                $number *= 1024;
        }

        return $number;
    }
}
