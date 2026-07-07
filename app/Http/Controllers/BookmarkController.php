<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessBookmark;
use App\Jobs\Rss\AdoptBookmarkFeed;
use App\Models\Bookmark;
use App\Models\RssFeed;
use App\Services\Audit;
use App\Services\BookmarkImporter;
use App\Support\FileResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

/**
 * The Launchpad: a catalog of internal tool links organized into folders.
 * Each new/imported bookmark is health-checked + hydrated (favicon, screenshot)
 * by a queued ProcessBookmark job.
 */
class BookmarkController extends Controller
{
    public function index(Request $request)
    {
        $userId = auth()->id();
        $search = trim((string) $request->string('search'));

        if ($search !== '') {
            $bookmarks = Bookmark::search($search)
                ->query(fn ($q) => $q->visibleTo($userId))
                ->get();
        } else {
            $bookmarks = Bookmark::visibleTo($userId)
                ->orderBy('sort_order')->orderBy('title')->get();
        }

        $subscribedUrls = RssFeed::where('user_id', $userId)->pluck('url')->flip();

        return Inertia::render('Bookmarks/Index', [
            'bookmarks' => $bookmarks->map(fn (Bookmark $b) => $this->shape($b, $userId, $subscribedUrls))->values(),
            'filters' => ['search' => $search ?: null],
            'screenshotsEnabled' => (bool) config('bookmarks.screenshots.enabled'),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Bookmark::class);
        $data = $this->validateData($request);

        $bookmark = Bookmark::create($data + ['owner_id' => auth()->id()]);
        ProcessBookmark::dispatch($bookmark->id);

        return back()->with('success', 'Bookmark added.');
    }

    public function update(Request $request, Bookmark $bookmark)
    {
        $this->authorize('update', $bookmark);

        $data = $this->validateData($request);
        $urlChanged = $data['url'] !== $bookmark->url;
        $bookmark->update($data);

        // Re-check liveness/favicon/screenshot if the URL changed.
        if ($urlChanged) {
            ProcessBookmark::dispatch($bookmark->id);
        }

        return back()->with('success', 'Bookmark updated.');
    }

    public function destroy(Bookmark $bookmark)
    {
        $this->authorize('delete', $bookmark);
        $bookmark->delete();

        return back()->with('success', 'Bookmark removed.');
    }

    /** Import a Chrome/Firefox bookmarks HTML export for the current user. */
    public function import(Request $request, BookmarkImporter $importer)
    {
        $this->authorize('create', Bookmark::class);
        $request->validate(['file' => 'required|file|mimes:html,htm|max:10240']);

        $userId = auth()->id();
        $count = 0;

        // Parse the full file into memory (bounded by 10 MB upload limit), then
        // check which URLs already exist in chunks rather than loading the user's
        // entire bookmark set into PHP memory.
        $links = $importer->parse($request->file('file')->get());
        $importUrls = array_column($links, 'url');

        $existingSet = collect();
        foreach (array_chunk($importUrls, 500) as $chunk) {
            $existingSet = $existingSet->concat(
                Bookmark::where('owner_id', $userId)->whereIn('url', $chunk)->pluck('url')
            );
        }
        $existingSet = $existingSet->flip();

        foreach ($links as $link) {
            if ($existingSet->has($link['url'])) {
                continue;
            }
            $bookmark = Bookmark::create([
                'owner_id' => $userId,
                'title' => Str::limit($link['title'], 120, ''),
                'url' => $link['url'],
                'category' => $link['category'] ? Str::limit($link['category'], 60, '') : null,
            ]);
            ProcessBookmark::dispatch($bookmark->id);
            $existingSet->put($link['url'], true);
            $count++;
        }

        Audit::log('bookmark.import', null, ['count' => $count]);

        return back()->with('success', "Imported {$count} bookmark(s). Validating in the background…");
    }

    /** Remove duplicate URLs the user owns, keeping the earliest of each. */
    public function dedup()
    {
        $userId = auth()->id();
        $removed = 0;

        Bookmark::where('owner_id', $userId)
            ->orderBy('id')->get()
            ->groupBy('url')
            ->each(function ($group) use (&$removed) {
                foreach ($group->slice(1) as $dupe) {
                    $dupe->delete();
                    $removed++;
                }
            });

        Audit::log('bookmark.dedup', null, ['removed' => $removed]);

        return back()->with('success', "Removed {$removed} duplicate(s).");
    }

    /** Delete every bookmark the user owns that failed its last liveness check. */
    public function prune()
    {
        $removed = Bookmark::where('owner_id', auth()->id())
            ->where('status', Bookmark::STATUS_DEAD)->delete();

        Audit::log('bookmark.prune', null, ['removed' => $removed]);

        return back()->with('success', "Removed {$removed} dead link(s).");
    }

    /** Re-check liveness + re-hydrate every bookmark the user owns. */
    public function validateAll()
    {
        $ids = Bookmark::where('owner_id', auth()->id())->pluck('id');
        $ids->each(fn ($id) => ProcessBookmark::dispatch($id));

        return back()->with('success', "Re-checking {$ids->count()} bookmark(s) in the background…");
    }

    /** Hydrate only bookmarks still missing a favicon/screenshot (or unchecked). */
    public function hydrate()
    {
        $wantsShots = (bool) config('bookmarks.screenshots.enabled');

        $ids = Bookmark::where('owner_id', auth()->id())
            ->where(fn ($q) => $q
                ->whereNull('icon_path')
                ->orWhere('status', Bookmark::STATUS_PENDING)
                ->when($wantsShots, fn ($w) => $w->orWhereNull('screenshot_path')))
            ->pluck('id');
        $ids->each(fn ($id) => ProcessBookmark::dispatch($id));

        return back()->with('success', "Hydrating {$ids->count()} bookmark(s) in the background…");
    }

    /** Toggle the starred flag. */
    public function star(Bookmark $bookmark)
    {
        $this->authorize('update', $bookmark);
        $bookmark->update(['starred' => ! $bookmark->starred]);

        return back();
    }

    /** Subscribe the current user's reader to the bookmark's detected feed. */
    public function subscribeFeed(Bookmark $bookmark)
    {
        $this->authorize('view', $bookmark);

        if (! $bookmark->feed_url) {
            return back()->withErrors(['feed' => 'This bookmark has no detected RSS feed.']);
        }

        $userId = auth()->id();
        $already = RssFeed::where('user_id', $userId)->where('url', $bookmark->feed_url)->exists();

        AdoptBookmarkFeed::dispatchSync($bookmark->id, $userId);

        return back()->with('success', $already ? 'Already subscribed to this feed.' : 'Subscribed. First fetch is running in the background…');
    }

    /** Count a click and bounce to the target URL. */
    public function go(Bookmark $bookmark)
    {
        $this->authorize('view', $bookmark);

        $bookmark->increment('click_count');
        Audit::log('bookmark.open', null, ['id' => $bookmark->id, 'url' => $bookmark->url]);

        return redirect()->away($bookmark->url);
    }

    /** Serve the stored favicon blob (policy-gated). */
    public function icon(Bookmark $bookmark)
    {
        $this->authorize('view', $bookmark);
        $disk = Storage::disk(config('filemanager.disk'));
        abort_unless($bookmark->icon_path && $disk->exists($bookmark->icon_path), 404);

        return FileResponse::serve($disk, $bookmark->icon_path, 'icon', $disk->mimeType($bookmark->icon_path) ?: 'image/x-icon');
    }

    /** Serve the stored screenshot blob (policy-gated). */
    public function screenshot(Bookmark $bookmark)
    {
        $this->authorize('view', $bookmark);
        $disk = Storage::disk(config('filemanager.disk'));
        abort_unless($bookmark->screenshot_path && $disk->exists($bookmark->screenshot_path), 404);

        return FileResponse::serve($disk, $bookmark->screenshot_path, 'screenshot.png', 'image/png');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:120',
            'url' => 'required|url:http,https|max:2048',
            'description' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:120',
            'color' => 'nullable|string|max:30',
            'category' => 'nullable|string|max:60',
            'shared' => 'boolean',
        ]);
    }

    /** Stored self-hosted screenshot, else an on-demand mShots thumbnail (if enabled). */
    private function screenshotUrl(Bookmark $bookmark): ?string
    {
        if ($bookmark->screenshot_path) {
            return route('bookmarks.screenshot', $bookmark);
        }
        if (config('bookmarks.screenshots.fallback')) {
            return 'https://s.wordpress.com/mshots/v1/'.urlencode($bookmark->url).'?w=1366&h=768';
        }

        return null;
    }

    /** DuckDuckGo favicon hotlink — the instant fallback before the job stores one. */
    private function faviconHotlink(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return $host ? "https://icons.duckduckgo.com/ip3/{$host}.ico" : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function shape(Bookmark $bookmark, int $userId, ?\Illuminate\Support\Collection $subscribedUrls = null): array
    {
        // A user-typed bootstrap icon name overrides imagery only when no blob exists.
        $iconName = $bookmark->icon && ! str_starts_with($bookmark->icon, 'http') ? $bookmark->icon : null;
        $iconUrl = $bookmark->icon_path
            ? route('bookmarks.icon', $bookmark)
            : ($iconName ? null : $this->faviconHotlink($bookmark->url));

        return [
            'id' => $bookmark->id,
            'title' => $bookmark->title,
            'url' => $bookmark->url,
            'description' => $bookmark->description,
            'icon_name' => $iconName,
            'icon_url' => $iconUrl,
            'screenshot_url' => $this->screenshotUrl($bookmark),
            'feed_url' => $bookmark->feed_url,
            'feed_subscribed' => $bookmark->feed_url && $subscribedUrls?->has($bookmark->feed_url) === true,
            'status' => $bookmark->status,
            'color' => $bookmark->color,
            'category' => $bookmark->category,
            'shared' => $bookmark->shared,
            'starred' => $bookmark->starred,
            'click_count' => $bookmark->click_count,
            'can_edit' => Gate::check('update', $bookmark),
        ];
    }
}
