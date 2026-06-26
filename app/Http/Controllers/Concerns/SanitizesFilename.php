<?php

namespace App\Http\Controllers\Concerns;

trait SanitizesFilename
{
    /**
     * Allowlist-sanitize an uploaded filename: strip path components, keep
     * only word chars, dash, dot, space and parens, collapse the rest to '_',
     * and never allow a leading dot / empty result.
     */
    protected function sanitizeFilename(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[^\w\-. ()]+/u', '_', $name) ?? '';
        $name = ltrim(trim($name), '.');

        return $name !== '' ? mb_substr($name, 0, 255) : 'file';
    }
}
