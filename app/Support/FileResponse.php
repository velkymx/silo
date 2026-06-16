<?php

namespace App\Support;

use Illuminate\Contracts\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FileResponse
{
    /**
     * Mime types we are willing to render inline in the browser. Everything
     * else is served as an attachment so it can never execute in our origin
     * (e.g. text/html, image/svg+xml, *.xml — all stored-XSS vectors).
     */
    private const INLINE_MIME_PREFIXES = ['image/', 'audio/', 'video/'];

    private const INLINE_MIME_EXACT = ['application/pdf'];

    /** Inline-looking types that are actually script vectors — never inline. */
    private const NEVER_INLINE = [
        'image/svg+xml',
        'image/svg',
    ];

    public static function inlineAllowed(?string $mime): bool
    {
        $mime = strtolower(trim((string) $mime));
        if ($mime === '' || in_array($mime, self::NEVER_INLINE, true)) {
            return false;
        }
        if (in_array($mime, self::INLINE_MIME_EXACT, true)) {
            return true;
        }
        foreach (self::INLINE_MIME_PREFIXES as $prefix) {
            if (str_starts_with($mime, $prefix)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Serve a stored file, choosing inline vs attachment safely and always
     * sending X-Content-Type-Options: nosniff so the browser can't sniff a
     * served octet-stream back into executable HTML.
     */
    public static function serve(Filesystem $disk, string $path, string $name, ?string $mime): StreamedResponse
    {
        $inline = self::inlineAllowed($mime);
        $contentType = $inline ? ($mime ?: 'application/octet-stream') : 'application/octet-stream';
        $disposition = $inline ? 'inline' : 'attachment';

        return $disk->response($path, $name, [
            'Content-Type' => $contentType,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox;",
        ], $disposition);
    }
}
