<?php

namespace App\Http\Controllers;

use App\Models\SavedSearch;
use Illuminate\Http\Request;

class SavedSearchController extends Controller
{
    private const KEYS = ['search', 'date_from', 'date_to', 'size_min', 'size_max', 'ftype', 'tag'];

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'params' => 'required|array',
            'params.search' => 'nullable|string|max:255',
            'params.date_from' => 'nullable|date',
            'params.date_to' => 'nullable|date',
            'params.size_min' => 'nullable|numeric',
            'params.size_max' => 'nullable|numeric',
            'params.ftype' => 'nullable|string|max:30',
            'params.tag' => 'nullable|integer',
        ]);

        // Keep only known filter keys with non-empty values.
        $params = collect($data['params'])
            ->only(self::KEYS)
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->all();

        SavedSearch::create([
            'owner_id' => auth()->id(),
            'name' => $data['name'],
            'params' => $params,
        ]);

        return back()->with('success', 'Smart folder saved.');
    }

    public function destroy(SavedSearch $savedSearch)
    {
        $this->authorize('delete', $savedSearch);
        $savedSearch->delete();

        return back()->with('success', 'Smart folder removed.');
    }
}
