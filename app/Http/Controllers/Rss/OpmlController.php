<?php

namespace App\Http\Controllers\Rss;

use App\Http\Controllers\Controller;
use App\Http\Requests\Rss\ImportOpmlFromUrlRequest;
use App\Http\Requests\Rss\ImportOpmlRequest;
use App\Jobs\Rss\ImportOpml;
use App\Models\RssFeed;
use App\Services\Rss\OpmlExporter;
use App\Services\Rss\SafeUrl;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

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

    /**
     * Import OPML by URL — covers the Feedly / Inoreader / NetNewsWire
     * export-URL flow without a file download step. The URL is fetched
     * once, the body is forwarded to the same queued ImportOpml job as
     * the file path, so all parsing and dedup rules stay in one place.
     */
    public function importFromUrl(ImportOpmlFromUrlRequest $request, SafeUrl $safeUrl): RedirectResponse
    {
        $url = $request->string('url')->toString();

        if (! $safeUrl->isSafe($url)) {
            return back()->with('error', 'That URL is not allowed (it must be a public http(s) address).');
        }

        try {
            $response = Http::withHeaders(['User-Agent' => 'Silo-OPML-Import/1.0'])
                ->timeout(15)
                ->withOptions(['allow_redirects' => $safeUrl->allowRedirects(5)])
                ->get($url);
        } catch (Throwable $e) {
            return back()->with('error', 'Could not fetch that URL: '.$e->getMessage());
        }

        if (! $response->successful()) {
            return back()->with('error', 'OPML endpoint returned HTTP '.$response->status().'.');
        }

        $xml = $response->body();
        if (strlen($xml) > 5 * 1024 * 1024) {
            return back()->with('error', 'OPML file is too large (5MB cap).');
        }

        ImportOpml::dispatch($request->user()->id, $xml);

        return back()->with('success', 'OPML from URL queued. Feeds will appear shortly.');
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
