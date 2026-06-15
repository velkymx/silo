<?php

namespace App\Http\Middleware;

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
        return [
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
            // Always available so the sidebar storage meter is consistent on every page.
            'storage' => fn () => $request->user()
                ? app(\App\Services\QuotaService::class)->summary($request->user()->id)
                : null,
            'currentFolder' => fn () => $request->integer('folder') ?: null,
            // Saved searches (smart folders) for the sidebar.
            'savedSearches' => fn () => $request->user()
                ? \App\Models\SavedSearch::where('owner_id', $request->user()->id)
                    ->orderBy('name')->get(['id', 'name', 'params'])
                : [],
        ];
    }
}
