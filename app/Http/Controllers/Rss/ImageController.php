<?php

namespace App\Http\Controllers\Rss;

use App\Http\Controllers\Controller;
use App\Services\Rss\ImageProxy;
use Illuminate\Http\Request;

/**
 * Serve a remote RSS image from our own origin. The route is signed (only the
 * app can mint these URLs) and auth-gated; the ImageProxy re-validates the
 * source URL and the downloaded bytes before anything is streamed.
 */
class ImageController extends Controller
{
    public function show(Request $request, ImageProxy $images)
    {
        $source = $request->query('url');
        if (! is_string($source) || $source === '') {
            abort(404);
        }

        $bytes = $images->bytes($source);
        $mime = $bytes === null ? null : $images->mime($bytes);
        if ($mime === null) {
            abort(404);
        }

        // Setting the CSP here also tells SecurityHeaders not to clobber it
        // with the app-wide policy — same pattern as FeedController::favicon.
        return response($bytes, 200, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; style-src 'unsafe-inline'; sandbox",
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}
