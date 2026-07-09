<?php

namespace App\Http\Controllers;

use App\Services\PlatformSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cross-content-type search surface. The global search input in the
 * navbar routes here; the page renders one section per indexed model
 * (files, rss, bookmarks) so the user sees everything their query
 * matched in a single result.
 */
class SearchController extends Controller
{
    public function index(Request $request, PlatformSearch $search): Response
    {
        $query = (string) $request->string('q')->toString();
        $results = $query === '' ? ['files' => [], 'rss' => [], 'bookmarks' => []] : $search->search($request->user()->id, $query);

        $total = array_sum(array_map('count', $results));

        return Inertia::render('Search/Index', [
            'q' => $query,
            'results' => $results,
            'total' => $total,
        ]);
    }

    /**
     * Live grouped results for the command palette. One call per keystroke
     * (debounced client-side), scoped to the acting user, capped per group.
     */
    public function quick(Request $request, PlatformSearch $search): JsonResponse
    {
        $query = (string) $request->string('q')->toString();
        $scope = $request->string('scope')->toString();
        $scope = in_array($scope, ['all', 'files', 'notes', 'rss', 'bookmarks'], true) ? $scope : 'all';

        return response()->json([
            'scope' => $scope,
            'results' => $search->quick($request->user()->id, $query, $scope),
        ]);
    }
}
