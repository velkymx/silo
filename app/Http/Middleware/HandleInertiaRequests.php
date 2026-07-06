<?php

namespace App\Http\Middleware;

use App\Models\Notification;
use App\Models\SavedSearch;
use App\Services\QuotaService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $base = [
            ...parent::share($request),
            'auth' => [
                'user' => $request->user()
                    ? array_merge(
                        $request->user()->only('id', 'name', 'email', 'is_admin', 'group_id'),
                        ['avatar_url' => $request->user()->avatar_path ? route('users.avatar', $request->user()) : null],
                    )
                    : null,
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'currentFolder' => fn () => $request->integer('folder') ?: null,
            // File-type category keys + labels, mirroring JS FILE_CATEGORIES.
            'fileCategories' => array_map(
                fn ($cat) => $cat['label'],
                config('file_categories', []),
            ),
        ];

        // ME-02: the sidebar (storage meter + smart folders) only renders on
        // Inertia pages. Skip the DB queries for streaming, downloads, and
        // other endpoints that don't use AppLayout.
        if ($this->rendersSidebar($request)) {
            $base['storage'] = fn () => $request->user()
                ? app(QuotaService::class)->summary($request->user()->id)
                : null;
            $base['savedSearches'] = fn () => $request->user()
                ? SavedSearch::where('owner_id', $request->user()->id)
                    ->orderBy('name')->get(['id', 'name', 'params'])
                : [];
        }

        if ($request->user()) {
            $base['notifications'] = fn () => [
                'unread_count' => Notification::ownedBy($request->user()->id)->unread()->count(),
                'recent' => Notification::ownedBy($request->user()->id)
                    ->orderByDesc('created_at')
                    ->limit(5)
                    ->get(['id', 'type', 'severity', 'title', 'url', 'read_at', 'created_at']),
            ];
        }

        return $base;
    }

    /**
     * Sidebar (storage + saved searches) is only relevant on authenticated
     * Inertia pages. API endpoints, file streaming, downloads, and public
     * share views don't need it — gating prevents needless DB hits.
     */
    private function rendersSidebar(Request $request): bool
    {
        if (! $request->user()) {
            return false;
        }
        $sidebar = 'files.* photos.* bookmarks.* notes.* vault.* directory.* trash.* '
            .'shared.* admin.* profile.* storage.* break.* search.*';

        return $request->routeIs(...array_map('trim', explode(' ', $sidebar)));
    }
}
