<?php

namespace App\Http\Controllers\Rss;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rss\ImportOpmlRequest;
use App\Jobs\Rss\ImportOpml;
use App\Models\RssFeed;
use App\Services\Rss\OpmlExporter;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * OPML surface: bulk import (queued) and bulk export (streamed). The
 * controller stays thin — both directions are pure XML in/out, so all
 * the logic lives in App\Services\Rss\Opml{Parser,Exporter}.
 */
class OpmlController extends Controller
{
    public function store(ImportOpmlRequest $request): RedirectResponse
    {
        $xml = $request->file('opml')->get();
        ImportOpml::dispatch($request->user()->id, $xml);

        return back()->with('success', 'OPML queued. Feeds will appear shortly.');
    }

    public function export(OpmlExporter $exporter): StreamedResponse
    {
        $userId = auth()->id();
        $feeds = RssFeed::ownedBy($userId)
            ->whereNull('muted_at')
            ->orderBy('folder')->orderBy('title')
            ->get();

        $filename = 'subscriptions-'.now()->format('Y-m-d').'.opml';

        return response()->streamDownload(function () use ($exporter, $feeds) {
            echo $exporter->build($feeds);
        }, $filename, [
            'Content-Type' => 'text/x-opml; charset=UTF-8',
        ]);
    }
}
