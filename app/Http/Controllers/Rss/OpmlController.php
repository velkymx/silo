<?php

namespace App\Http\Controllers\Rss;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rss\ImportOpmlRequest;
use App\Jobs\Rss\ImportOpml;
use Illuminate\Http\RedirectResponse;

/**
 * OPML bulk-import surface. The controller is intentionally tiny — the
 * upload is validated, handed to a queued ImportOpml job, and the user is
 * sent back with a confirmation. Parsing happens in the worker so a 5MB
 * OPML file does not block the HTTP request.
 */
class OpmlController extends Controller
{
    public function store(ImportOpmlRequest $request): RedirectResponse
    {
        $xml = $request->file('opml')->get();
        ImportOpml::dispatch($request->user()->id, $xml);

        return back()->with('success', 'OPML queued. Feeds will appear shortly.');
    }
}
