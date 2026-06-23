<?php

namespace App\Http\Controllers;

use App\Models\Bookmark;
use App\Services\Audit;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * The Launchpad: a catalog of internal tool links. Personal bookmarks plus any
 * shared company-wide, grouped by category.
 */
class BookmarkController extends Controller
{
    public function index()
    {
        $userId = auth()->id();

        $bookmarks = Bookmark::visibleTo($userId)
            ->orderBy('sort_order')->orderBy('title')
            ->get()
            ->map(fn (Bookmark $b) => $this->shape($b, $userId));

        return Inertia::render('Bookmarks/Index', [
            'bookmarks' => $bookmarks->values(),
        ]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Bookmark::class);
        $data = $this->validateData($request);

        Bookmark::create($data + ['owner_id' => auth()->id()]);

        return back()->with('success', 'Bookmark added.');
    }

    public function update(Request $request, Bookmark $bookmark)
    {
        $this->authorize('update', $bookmark);

        $bookmark->update($this->validateData($request));

        return back()->with('success', 'Bookmark updated.');
    }

    public function destroy(Bookmark $bookmark)
    {
        $this->authorize('delete', $bookmark);

        $bookmark->delete();

        return back()->with('success', 'Bookmark removed.');
    }

    /** Count a click and bounce to the target URL. */
    public function go(Bookmark $bookmark)
    {
        $this->authorize('view', $bookmark);

        $bookmark->increment('click_count');
        Audit::log('bookmark.open', null, ['id' => $bookmark->id, 'url' => $bookmark->url]);

        return redirect()->away($bookmark->url);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request): array
    {
        return $request->validate([
            'title' => 'required|string|max:120',
            'url' => 'required|url|max:2048',
            'description' => 'nullable|string|max:255',
            'icon' => 'nullable|string|max:120',
            'color' => 'nullable|string|max:30',
            'category' => 'nullable|string|max:60',
            'shared' => 'boolean',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function shape(Bookmark $bookmark, int $userId): array
    {
        return [
            'id' => $bookmark->id,
            'title' => $bookmark->title,
            'url' => $bookmark->url,
            'description' => $bookmark->description,
            'icon' => $bookmark->icon ?: 'link-45deg',
            'color' => $bookmark->color,
            'category' => $bookmark->category,
            'shared' => $bookmark->shared,
            'click_count' => $bookmark->click_count,
            // Only the owner (or an admin) sees edit/delete controls.
            'can_edit' => $bookmark->owner_id === $userId || auth()->user()->is_admin,
        ];
    }
}
