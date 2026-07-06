<?php

namespace App\Http\Controllers;

use App\Models\SavedSearch;
use Illuminate\Http\Request;

class SavedSearchController extends Controller
{
    /**
     * The two scopes share a table. The `kind` column (computed from the
     * params) distinguishes a file smart folder (has `search` or other
     * file-side keys) from a global saved search (has `q`).
     *
     * @var array<int, string>
     */
    private const FILE_KEYS = ['search', 'date_from', 'date_to', 'size_min', 'size_max', 'ftype', 'tag', 'folder'];

    /**
     * @var array<int, string>
     */
    private const GLOBAL_KEYS = ['q'];

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'params' => 'required|array',
            'params.q' => 'nullable|string|max:255',
            'params.search' => 'nullable|string|max:255',
            'params.date_from' => 'nullable|date',
            'params.date_to' => 'nullable|date',
            'params.size_min' => 'nullable|numeric',
            'params.size_max' => 'nullable|numeric',
            'params.ftype' => 'nullable|string|max:30',
            'params.tag' => 'nullable|integer',
            'params.folder' => 'nullable|integer',
        ]);

        $allowed = array_merge(self::FILE_KEYS, self::GLOBAL_KEYS);
        $params = collect($data['params'])
            ->only($allowed)
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->all();

        SavedSearch::create([
            'owner_id' => auth()->id(),
            'name' => $data['name'],
            'params' => $params,
        ]);

        return back()->with('success', empty($params['q'] ?? null) ? 'Smart folder saved.' : 'Search saved.');
    }

    public function destroy(SavedSearch $savedSearch)
    {
        $this->authorize('delete', $savedSearch);
        $savedSearch->delete();

        $wasGlobal = ! empty($savedSearch->params['q'] ?? null);
        $msg = $wasGlobal ? 'Saved search removed.' : 'Smart folder removed.';

        return back()->with('success', $msg);
    }
}
